<?php namespace Zen\Chub\Classes\System;

use Carbon\Carbon;
use DateTimeInterface;
use Throwable;
use DB;
use Zen\Chub\Classes\Enums\LogType;
use Zen\Chub\Classes\Support\SupportRocketBot;

class SystemChecks
{
    public static function make(): self
    {
        return new self();
    }

    # dotpath: Zen.Chub.Classes.System.SystemChecks.dailyCheck
    public function dailyCheck()
    {
        $check1 = SystemChecks::make()->freeSpaceAutoCheck();
        $check2 = SystemChecks::make()->heardBeatCheck();
        $check3 = SystemChecks::make()->logsCheck();

        # Формирование индикаторов
        $i1 = $this->indicator($check1['success']);
        $i2 = $this->indicator($check2['success']);
        $i3 = $this->indicator($check3['success']);

        # Формирование тектов сообщений
        $m1 = $check1['message'];
        $m2 = $check2['message'];
        $m3 = $check3['message'];  

        # Формирование  уведомления в RocketChat
        $message = "$i1 $m1<br>"
            . "$i2 $m2<br>"
            . "$i3 $m3";

        SupportRocketBot::make()->send($message);
    }

    public function indicator(bool $state)
    {
        return $state ? '🟢' : '🔴';
    }

    # Проверка на свободное место
    # http://axis/chub.api/Dev.SystemChecksDevelop:check1
    public function freeSpaceAutoCheck()
    {
        $info = $this->freeSpaceCheck();

        $size = $info['formated'];
        $success = false;

        if ($info['mb'] < 1024) {
            $message = "Внимание! Свободное место на диске заканчивается! [$size]";
        } else {
            $message = "Дисковое пространство [$size]";
            $success = true;
        }

        return [
            'success' => $success,
            'message' => $message
        ];
    }

    /**
     * Поминутный лог команды: разрывы между соседними записями > 1 мин за вчера [00:00, 00:00).
     *
     * @return array{success: bool, message: string}
     */
    public function heardBeatCheck(): array
    {
        $command_id = 1;
        $db_path = base_path('storage/chub/bases/command_log_' . $command_id . '.sqlite');

        if (!is_file($db_path)) {
            return [
                'success' => false,
                'message' => 'Файл лога команды не найден (id=' . $command_id . ').',
            ];
        }

        try {
            $sqlite = Sqlite::connect($db_path);
            if (!$sqlite->tableExists('records')) {
                return [
                    'success' => false,
                    'message' => 'В логе команды нет таблицы records.',
                ];
            }

            [$period_start, $period_end] = $this->getYesterdayLogPeriod();

            $rows = $sqlite->query('records')
                ->where('created_at', '>=', $period_start->toDateTimeString())
                ->where('created_at', '<', $period_end->toDateTimeString())
                ->orderBy('created_at')
                ->get(['created_at']);
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Не удалось прочитать лог: ' . $exception->getMessage(),
            ];
        }

        if ($rows->isEmpty()) {
            return [
                'success' => false,
                'message' => 'За вчерашний день нет записей лога (ожидался поминутный сигнал).',
            ];
        }

        $beat_minutes = $this->collectSortedUniqueMinuteMarks($rows);
        if ($beat_minutes === []) {
            return [
                'success' => false,
                'message' => 'За вчерашний день в логе нет ни одной записи с заполненным created_at.',
            ];
        }

        $gaps = $this->findMinuteGapsInWindow($period_start, $period_end, $beat_minutes);

        if ($gaps === []) {
            return [
                'success' => true,
                'message' => 'Непрерывный аптайм',
            ];
        }

        $parts = [];
        foreach ($gaps as $gap) {
            $parts[] = $gap['at']->format('H:i') . ' (' . $gap['minutes'] . ' мин)';
        }

        return [
            'success' => false,
            'message' => 'Обнаружены простои системы: ' . implode(', ', $parts),
        ];
    }

    /**
     * Логи OCMS и Chub за вчера [00:00, 00:00): число записей уровня ошибка.
     *
     * @return array{success: bool, message: string}
     */
    public function logsCheck(): array
    {
        [$period_start, $period_end] = $this->getYesterdayLogPeriod();

        try {
            $system_errors = (int) DB::table('system_event_logs')
                ->where('created_at', '>=', $period_start->toDateTimeString())
                ->where('created_at', '<', $period_end->toDateTimeString())
                ->whereRaw('LOWER(COALESCE(TRIM(level), ?)) IN (?, ?, ?, ?)', [
                    '',
                    'error',
                    'critical',
                    'alert',
                    'emergency',
                ])
                ->count();
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Не удалось прочитать system_event_logs: ' . $exception->getMessage(),
            ];
        }

        $chub_errors = 0;
        $logs_path = storage_path('chub/bases/logs.sqlite');
        if (is_file($logs_path)) {
            try {
                $sqlite = Sqlite::connect($logs_path);
                if ($sqlite->tableExists('records')) {
                    $chub_errors = (int) $sqlite->query('records')
                        ->where('created_at', '>=', $period_start->toDateTimeString())
                        ->where('created_at', '<', $period_end->toDateTimeString())
                        ->where('type', LogType::ERROR->value)
                        ->count();
                }
            } catch (Throwable $exception) {
                return [
                    'success' => false,
                    'message' => 'Не удалось прочитать лог CruiseHUB: ' . $exception->getMessage(),
                ];
            }
        }

        if ($system_errors === 0 && $chub_errors === 0) {
            return [
                'success' => true,
                'message' => 'Ошибок не зафиксировано',
            ];
        }

        return [
            'success' => false,
            'message' => 'Системных ошибок: ' . $system_errors . ', CruiseHUB ошибок: ' . $chub_errors,
        ];
    }

    /** @return array{0: mixed, 1: mixed} [вчера 00:00, сегодня 00:00) */
    private function getYesterdayLogPeriod(): array
    {
        $period_end = now()->startOfDay();
        $period_start = $period_end->copy()->subDay();

        return [$period_start, $period_end];
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows
     * @return list<Carbon>
     */
    private function collectSortedUniqueMinuteMarks($rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            if (!isset($row->created_at) || $row->created_at === null) {
                continue;
            }
            $minute = Carbon::parse($row->created_at)->startOfMinute();
            $key = $minute->getTimestamp();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $minute;
        }

        return $out;
    }

    /**
     * @param list<Carbon> $beat_minutes
     * @return list<array{at: Carbon, minutes: int}>
     */
    private function findMinuteGapsInWindow(DateTimeInterface $period_start, DateTimeInterface $period_end, array $beat_minutes): array
    {
        $window_start = Carbon::parse($period_start)->startOfMinute();
        $window_end = Carbon::parse($period_end)->startOfMinute();

        $gaps = [];
        $prev = $window_start;

        foreach ($beat_minutes as $beat) {
            $diff_seconds = $beat->getTimestamp() - $prev->getTimestamp();
            if ($diff_seconds > 60) {
                $gaps[] = [
                    'at' => $prev,
                    'minutes' => intdiv($diff_seconds, 60),
                ];
            }
            $prev = $beat;
        }

        $diff_seconds = $window_end->getTimestamp() - $prev->getTimestamp();
        if ($diff_seconds > 60) {
            $gaps[] = [
                'at' => $prev,
                'minutes' => intdiv($diff_seconds, 60),
            ];
        }

        return $gaps;
    }


    /**
     * Свободное место на ФС текущей рабочей директории (в контейнере — обычно корень тома приложения).
     * Поле `mb` — целое число мебибайт (1 MiB = 1024² байт), не десятичных «мегабайт».
     *
     * @return array{mb: int, formated: string}
     */
    public function freeSpaceCheck(): array
    {
        $unavailable = ['mb' => 0, 'formated' => 'недоступно'];

        if (!function_exists('shell_exec')) {
            return $unavailable;
        }

        $output = @shell_exec('df -Pk . 2>/dev/null');
        if ($output === null || $output === false) {
            return $unavailable;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', trim($output)))));
        if (count($lines) < 2) {
            return $unavailable;
        }

        $fields = preg_split('/\s+/', $lines[1]);
        if (!isset($fields[3]) || !is_numeric($fields[3])) {
            return $unavailable;
        }

        $avail_kib = (int) $fields[3];
        $bytes = $avail_kib * 1024;
        $mib = (int) round($bytes / (1024 * 1024));

        return [
            'mb' => $mib,
            'formated' => $this->formatBinaryDiskLabel($mib),
        ];
    }

    /** Двоичные единицы IEC (1024): MiB / GiB / TiB. */
    private function formatBinaryDiskLabel(int $mib): string
    {
        if ($mib < 1024) {
            return $mib . ' MiB';
        }
        if ($mib < 1024 * 1024) {
            if ($mib % 1024 === 0) {
                return ((int) ($mib / 1024)) . ' GiB';
            }

            return number_format($mib / 1024, 1, '.', '') . ' GiB';
        }
        $mib_per_tib = 1024 * 1024;
        if ($mib % $mib_per_tib === 0) {
            return ((int) ($mib / $mib_per_tib)) . ' TiB';
        }

        return number_format($mib / $mib_per_tib, 1, '.', '') . ' TiB';
    }
}