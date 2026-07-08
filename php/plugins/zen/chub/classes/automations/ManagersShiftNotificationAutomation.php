<?php namespace Zen\Chub\Classes\Automations;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Zen\Chub\Classes\Connectors\RocketChatBot;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Classes\System\StatesApp;

/**
 * Ежедневное уведомление «кто в смене» по графику из Google Таблицы (экспорт CSV).
 *
 * Dotpath: Zen.Chub.Classes.Automations.ManagersShiftNotificationAutomation.handle
 *
 * Окружение:
 * - CHUB_MANAGERS_SHIFT_SHEET_CSV_URL — URL экспорта CSV опубликованной таблицы (обязательно).
 * - CHUB_MANAGERS_SHIFT_ROCKET_WEBHOOK_URL — optional incoming webhook для канала (например Oplata); иначе тот же, что у {@see RocketChatBot}.
 *
 * Отправка только в Rocket.Chat; флаг `notify-settings.atm_rocket_chat_bot_enabled` должен быть включён.
 */
class ManagersShiftNotificationAutomation
{
    private const WORK_MARK = '1';

    public static function make(): self
    {
        return new self();
    }

    public function handle(): void
    {
        if (!boolval(StatesApp::getSetting('notify-settings.atm_rocket_chat_bot_enabled'))) {
            return;
        }

        $csv_url = trim((string) env('CHUB_MANAGERS_SHIFT_SHEET_CSV_URL', ''));

        if ($csv_url === '') {
            Log::warning('ManagersShiftNotificationAutomation: CHUB_MANAGERS_SHIFT_SHEET_CSV_URL is empty');

            return;
        }

        $body = $this->fetchCsvBody($csv_url);
        if ($body === null) {
            return;
        }

        $rows = $this->parseCsvRows($body);
        if ($rows === []) {
            Log::warning('ManagersShiftNotificationAutomation: empty CSV');

            return;
        }


        $today = Carbon::now()->startOfDay();
        $today_column = $this->findTodayColumn($rows, $today);
        if ($today_column === null) {
            Log::warning('ManagersShiftNotificationAutomation: no column for today ' . $today->toDateString());

            return;
        }

        $on_duty = $this->collectSurnamesOnDuty(
            $rows,
            $today_column['month_row_index'],
            $today_column['col_index']
        );
        $message = $this->buildMessage($today, $on_duty);

        $webhook = trim((string) env('CHUB_MANAGERS_SHIFT_ROCKET_WEBHOOK_URL', ''));
        RocketChatBot::make()->sendMessage(
            $message,
            'HTML',
            $webhook !== '' ? $webhook : null
        );
    }

    private function fetchCsvBody(string $url): ?string
    {
        try {
            /** @var Response $response */
            $response = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            LogsApp::addErrorFromThrowable($e, 'ManagersShift: ошибка HTTP CSV', ['url' => $url]);

            return null;
        }

        if (!$response->successful()) {
            LogsApp::addError([
                'status' => $response->status(),
                'url' => $url,
            ], 'ManagersShift: HTTP ошибка CSV');

            return null;
        }

        $body = $response->body();
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        return $body;
    }

    /**
     * @return list<list<string>>
     */
    private function parseCsvRows(string $body): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }

        fwrite($stream, $body);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = array_map(static fn ($c) => is_string($c) ? trim($c) : '', $row);
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{month_row_index:int,col_index:int}|null
     */
    private function findTodayColumn(array $rows, Carbon $today): ?array
    {
        foreach ($rows as $month_row_index => $row) {
            foreach ($row as $col_index => $cell) {
                $parsed_date = $this->parseScheduleHeaderDate((string) $cell, $today);
                if ($parsed_date === null) {
                    continue;
                }

                if ($parsed_date->equalTo($today)) {
                    return [
                        'month_row_index' => $month_row_index,
                        'col_index' => $col_index,
                    ];
                }
            }
        }

        return null;
    }

    private function parseScheduleHeaderDate(string $cell, Carbon $today): ?Carbon
    {
        $cell = trim(str_replace("\u{00A0}", ' ', $cell));
        if ($cell === '') {
            return null;
        }

        $normalized_cell = mb_strtolower($cell);

        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}$/u', $normalized_cell) === 1) {
            try {
                return Carbon::createFromFormat('d.m.Y', $normalized_cell)->startOfDay();
            } catch (\Throwable) {
                // no-op: пробуем другие форматы ниже
            }
        }

        if (preg_match('/^(\d{1,2})\s+([а-яё]+)\.?/u', $normalized_cell, $matches) !== 1) {
            return null;
        }

        $day = intval($matches[1]);
        $month_token = mb_substr($matches[2], 0, 3);
        $month_by_token = [
            'янв' => 1,
            'фев' => 2,
            'мар' => 3,
            'апр' => 4,
            'мая' => 5,
            'май' => 5,
            'июн' => 6,
            'июл' => 7,
            'авг' => 8,
            'сен' => 9,
            'окт' => 10,
            'ноя' => 11,
            'дек' => 12,
        ];

        $month = $month_by_token[$month_token] ?? null;
        if ($month === null) {
            return null;
        }

        try {
            return Carbon::create($today->year, $month, $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<string>
     */
    private function collectSurnamesOnDuty(array $rows, int $month_row_index, int $col_index): array
    {
        $out = [];

        $start_row_index = $month_row_index + 2;
        for ($row_index = $start_row_index, $max = count($rows); $row_index < $max; $row_index++) {
            $row = $rows[$row_index] ?? [];
            $employee_number = trim((string) ($row[0] ?? ''));
            $surname = trim((string) ($row[1] ?? ''));

            if ($surname === '' && $employee_number === '') {
                continue;
            }

            if (mb_stripos($surname, 'Продавцов в смене') !== false) {
                break;
            }

            if ($employee_number === '' || !preg_match('/^\d+$/', $employee_number)) {
                continue;
            }

            $mark = trim((string) ($row[$col_index] ?? ''));
            if ($mark === self::WORK_MARK) {
                $out[] = $surname;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $surnames
     */
    private function buildMessage(Carbon $today, array $surnames): string
    {
        $date_str = $today->format('d.m.Y');
        $dow_short = $this->shortWeekdayRu($today);
        $n = count($surnames);
        $human_word = $this->pluralHumansRu($n);
        $verb = $n === 1 ? 'работает' : 'работают';

        $lines = [
            "🔆 Сегодня {$date_str} ({$dow_short}) в смене {$verb} {$n} {$human_word}:",
        ];

        foreach ($surnames as $i => $name) {
            $lines[] = ($i + 1) . '. ' . htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return implode("\n", $lines);
    }

    private function shortWeekdayRu(Carbon $date): string
    {
        static $map = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];

        return $map[(int) $date->format('w')] ?? '';
    }

    private function pluralHumansRu(int $n): string
    {
        $n = abs($n);
        $n10 = $n % 10;
        $n100 = $n % 100;
        if ($n10 === 1 && $n100 !== 11) {
            return 'человек';
        }
        if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
            return 'человека';
        }

        return 'человек';
    }
}
