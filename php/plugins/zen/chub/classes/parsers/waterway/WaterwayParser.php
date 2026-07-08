<?php namespace Zen\Chub\Classes\Parsers\Waterway;

use Carbon\Carbon;
use Exception;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Dto\CheckinDto;

class WaterwayParser
{
    private const string BASE_CODE = 'parser-db-waterway';
    private const string SOURCE_URL = 'https://api-crs.vodohod.com';

    private ?BasesApp $waterway_db = null;
    private ?WaterwayApiClient $api_client = null;
    private array $category_cache = [];

    public static function make(): self
    {
        return new self();
    }

    public function importOrchestrator(): array
    {
        return [
            'parser-waterway-prepare-session',
            'parser-waterway-parse-ships',
            'parser-waterway-parse-cruise-details',
            'parser-waterway-clean-cruises-without-prices',
            'parser-waterway-finish-session',
            'waterway-db-push',
        ];
    }

    public function run(string|array|null $value = null): void
    {
        $process_code = is_array($value) ? (string) ($value['code'] ?? '') : (string) $value;
        $process_code = trim($process_code);
        if ($process_code === '') {
            return;
        }

        ProcessApp::make()->runScheduleProcess($process_code);
        $started_at = time();
        while (true) {
            sleep(1);
            if (!Stream::exists($process_code)) {
                if ((time() - $started_at) > 15) {
                    throw new Exception('Child stream state was not created: ' . $process_code);
                }
                continue;
            }

            $stream = Stream::connect($process_code);
            if ($stream->isCompleted() || $stream->isError() || $stream->isStopped()) {
                break;
            }

            if (!$stream->inProcess() && (time() - $started_at) > 30) {
                if ($this->isNoopIdleStream($stream)) {
                    break;
                }
                throw new Exception('Child stream is not running and not completed: ' . $process_code);
            }

            if ((time() - $started_at) > 7200) {
                throw new Exception('Child stream timeout exceeded: ' . $process_code);
            }
        }

        $stream = Stream::connect($process_code);
        if ($stream->isError()) {
            throw new Exception('Child stream failed: ' . $process_code . '; ' . (string) $stream->getErrorMessage());
        }
        if ($stream->isStopped()) {
            throw new Exception('Child stream stopped before completion: ' . $process_code);
        }
    }

    private function isNoopIdleStream(Stream $stream): bool
    {
        $batches_total = intval($stream->getStateData('batches_total') ?? 0);
        $batches_processed = intval($stream->getStateData('batches_processed') ?? 0);
        $process_pid = intval($stream->getProcessPid() ?? 0);

        return !$stream->inProcess()
            && !$stream->isCompleted()
            && !$stream->isError()
            && !$stream->isStopped()
            && $process_pid <= 0
            && $batches_total === 0
            && $batches_processed === 0;
    }

    public function processPrepareSession(): int
    {
        return $this->startSession();
    }

    public function processParseShips(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        $client = $this->getApiClient();
        $ships = $client->getMotorships();
        $now = now()->toDateTimeString();
        $count = 0;
        foreach ($ships as $ship_id => $ship) {
            $this->query('ships')->updateOrInsert(
                ['id' => (int) $ship_id],
                [
                    'id' => (int) $ship_id,
                    'name' => (string) ($ship['name'] ?? ''),
                    'description' => (string) ($ship['description'] ?? ''),
                    'type' => $ship['type'] ?? null,
                    'raw_json' => json_encode($ship, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $count++;
        }

        $cruises = $client->getCruises();
        foreach ($cruises as $cruise_id => $cruise) {
            $ship_id = (int) ($cruise['motorshipId'] ?? 0);
            if ($ship_id <= 0) {
                continue;
            }
            $date_start = $cruise['dateStart'] ?? null;
            $date_end = $cruise['dateStop'] ?? null;
            if (!$date_start || !$date_end) {
                continue;
            }
            $this->query('cruises')->updateOrInsert(
                ['id' => (int) $cruise_id],
                [
                    'id' => (int) $cruise_id,
                    'ship_id' => $ship_id,
                    'name' => (string) ($cruise['name'] ?? ''),
                    'route' => $this->extractRouteFromName((string) ($cruise['name'] ?? '')),
                    'date_start' => $date_start . ' 00:00:00',
                    'date_end' => $date_end . ' 00:00:00',
                    'days' => (int) ($cruise['days'] ?? 0),
                    'description' => $cruise['classDescription'] ?? null,
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        return $count;
    }

    public function createCruiseBatches(): array
    {
        $session_id = $this->getRequiredActiveSessionId();
        $rows = $this->query('cruises')
            ->where('session_id', $session_id)
            ->orderBy('id')
            ->get();
        if ($rows->isEmpty()) {
            return [['__noop' => true]];
        }
        $batches = [];
        foreach ($rows as $row) {
            $batches[] = ['cruise_id' => (int) $row->id];
        }
        return $batches;
    }

    public function handleCruiseBatch(?array $item = null): int
    {
        if ($item === null || empty($item) || !empty($item['__noop'])) {
            return 0;
        }
        $cruise_id = (int) ($item['cruise_id'] ?? 0);
        if ($cruise_id <= 0) {
            return 0;
        }
        $session_id = $this->getRequiredActiveSessionId();
        $row = $this->query('cruises')->find($cruise_id);
        if (!$row) {
            return 0;
        }

        $client = $this->getApiClient();
        $routes = $client->getCruiseRoute($cruise_id);
        $prices_data = $client->getCruisePrices($cruise_id);
        if (!$prices_data || !isset($prices_data['tariffs'])) {
            return 0;
        }

        $cruise_list = [
            'name' => (string) ($row->name ?? ''),
            'motorshipId' => (int) ($row->ship_id ?? 0),
            'dateStart' => substr((string) ($row->date_start ?? ''), 0, 10),
            'dateStop' => substr((string) ($row->date_end ?? ''), 0, 10),
            'days' => (int) ($row->days ?? 0),
            'classDescription' => $row->description,
        ];

        $payload = $this->buildCruisePayload($cruise_list, $cruise_id, $routes);
        $now = now()->toDateTimeString();
        $this->query('cruises')->where('id', $cruise_id)->update([
            'route' => $payload['route'],
            'date_start' => $payload['date_start'],
            'date_end' => $payload['date_end'],
            'date_start_precise' => $payload['date_start_precise'],
            'date_end_precise' => $payload['date_end_precise'],
            'days' => $payload['days'],
            'description' => $payload['description'],
            'schedule_html' => $payload['schedule_html'],
            'waybill_json' => $payload['waybill_json'],
            'raw_json' => json_encode(['routes' => $routes, 'list' => $cruise_list], JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        $this->query('waybills')->where('cruise_id', $cruise_id)->where('session_id', $session_id)->delete();
        $order = 0;
        foreach ($payload['waybill_rows'] as $wb) {
            $this->query('waybills')->insert([
                'cruise_id' => $cruise_id,
                'order_index' => $order++,
                'town_name' => $wb['town_name'],
                'excursion' => $wb['excursion'],
                'is_bold' => $wb['is_bold'],
                'raw_json' => null,
                'session_id' => $session_id,
                'created_at' => $now,
            ]);
        }

        $ship_id = (int) ($row->ship_id ?? 0);
        $this->persistPricesForCruise($cruise_id, $ship_id, $session_id, $prices_data, $now);

        return 1;
    }

    public function processCleanCruisesWithoutPrices(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        $cruise_ids = $this->query('cruises')
            ->where('session_id', $session_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $deleted = 0;
        foreach ($cruise_ids as $cid) {
            $has = $this->query('prices')->where('cruise_id', $cid)->exists();
            if ($has) {
                continue;
            }
            $this->query('waybills')->where('cruise_id', $cid)->delete();
            $this->query('cruises')->where('id', $cid)->delete();
            $deleted++;
        }
        return $deleted;
    }

    public function processFinishSession(): ?int
    {
        $session_id = $this->getActiveSessionId();
        if ($session_id === null) {
            throw new Exception('No active session to finish');
        }
        $this->finishSession($session_id);
        return $session_id;
    }

    public function prodBatches(): array
    {
        $this->ensureWaterwayDb();
        $cruises = [];
        $this->waterway_db->query('cruises')
            ->orderBy('id')
            ->chunk(100, function ($records) use (&$cruises) {
                foreach ($records as $record) {
                    $date_start = (string) ($record->date_start_precise ?? $record->date_start ?? '');
                    $date_end = (string) ($record->date_end_precise ?? $record->date_end ?? '');
                    $cruises[] = [
                        'cruise_id' => (int) $record->id,
                        'date_start' => $date_start,
                        'date_end' => $date_end,
                        'ship_id' => (int) ($record->ship_id ?? 0),
                    ];
                }
            });
        return $cruises;
    }

    public function prodTransit(array $cruise): void
    {
        $this->ensureWaterwayDb();
        $cruise_id = (int) ($cruise['cruise_id'] ?? 0);
        $ship_id = (int) ($cruise['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $ship_id <= 0) {
            return;
        }
        $ship_name = $this->getWaterwayShipName($ship_id);
        if ($ship_name === null || trim($ship_name) === '') {
            return;
        }

        $checkin_dto = CheckinDto::make(
            provider_code: 'vodohod',
            provider_checkin_id: $cruise_id,
            date_start: (string) ($cruise['date_start'] ?? ''),
            date_end: (string) ($cruise['date_end'] ?? ''),
        );
        $checkin_dto->resolveShip($ship_name, $ship_id);

        $waybill_rows = $this->waterway_db->query('waybills')
            ->where('cruise_id', $cruise_id)
            ->orderBy('order_index')
            ->get();
        foreach ($waybill_rows as $point) {
            $town = trim((string) ($point->town_name ?? ''));
            if ($town === '') {
                continue;
            }
            $checkin_dto->addWaybillPoint(
                town_name: $town,
                is_important: (int) ($point->is_bold ?? 0) > 0,
                point_type: null,
                arrival_at: null,
                departure_at: null,
                note: !empty($point->excursion) ? (string) $point->excursion : null,
            );
        }

        $prices = $this->waterway_db->query('prices')->where('cruise_id', $cruise_id)->get();
        foreach ($prices as $price_record) {
            $category_id = (int) ($price_record->cabin_category_id ?? 0);
            if ($category_id <= 0) {
                continue;
            }
            $category = $this->getWaterwayCategory($ship_id, $category_id);
            if (!$category) {
                continue;
            }
            $deck_id = (int) ($price_record->deck_id ?? 0);
            $deck = $deck_id > 0 ? $this->waterway_db->query('decks')->find($deck_id) : null;
            $places_main = max(1, (int) ($category->places ?? 1));
            $places_value = max(1, (int) ($price_record->places_qnt ?? $places_main));
            $price_value = (int) ($price_record->price_value ?? 0);
            if ($price_value <= 0) {
                continue;
            }
            $checkin_dto->resolvePrice(
                provider_grade_name: (string) ($category->name ?? ('Category ' . $category_id)),
                provider_grade_uid: (string) $category_id,
                places_main_qnt: $places_main,
                places_extra_qnt: 0,
                provider_deck_name: $deck?->name,
                provider_deck_uid: $deck ? (string) $deck->id : null,
                places_value: $places_value,
                price_value: $price_value,
                tariff_id: 1,
            );
            $price_extra = (int) ($price_record->price_extra ?? 0);
            if ($price_extra > 0) {
                $checkin_dto->resolvePrice(
                    provider_grade_name: (string) ($category->name ?? ('Category ' . $category_id)),
                    provider_grade_uid: (string) $category_id,
                    places_main_qnt: $places_main,
                    places_extra_qnt: 0,
                    provider_deck_name: $deck?->name,
                    provider_deck_uid: $deck ? (string) $deck->id : null,
                    places_value: $places_value,
                    price_value: $price_extra,
                    tariff_id: 2,
                );
            }
        }

        $checkin_dto->push();
    }

    public function startSession(): int
    {
        $this->closeRunningSessions('Interrupted by new parser run');
        $now = now()->toDateTimeString();
        return (int) $this->query('sessions')->insertGetId([
            'source_name' => 'waterway',
            'source_url' => self::SOURCE_URL,
            'status' => 'running',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function finishSession(int $session_id): void
    {
        $now = now()->toDateTimeString();
        $this->query('sessions')
            ->where('id', $session_id)
            ->update([
                'status' => 'completed',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function buildCruisePayload(array $cruise, int $cruise_id, ?array $routes): array
    {
        $ship_id = (int) ($cruise['motorshipId'] ?? 0);
        $name = (string) ($cruise['name'] ?? '');
        $date_start = $cruise['dateStart'] ?? null;
        $date_stop = $cruise['dateStop'] ?? null;
        $days = (int) ($cruise['days'] ?? 0);
        $description = $cruise['classDescription'] ?? null;
        $route = $this->extractRouteFromName($name);

        $schedule_html = '';
        $waybill_rows = [];
        $date_start_precise = null;
        $date_end_precise = null;
        $waybill_json = null;

        $has_valid_routes = $routes && is_array($routes) && !isset($routes['error']) && $routes !== [];

        if ($has_valid_routes) {
            $schedule_html = $this->processScheduleData($routes, (string) $date_start);
            $waybill_rows = $this->buildWaybillRowsFromRoutes($name, $routes);
            $dates = $this->processDates($routes, (string) $date_start, (string) $date_stop);
            $date_start_precise = $dates['date_start'];
            $date_end_precise = $dates['date_end'];
            if (count($waybill_rows) < 2) {
                $minimal = $this->buildMinimalFallback($cruise);
                if ($minimal) {
                    $waybill_rows = $minimal['waybill_rows'];
                    if ($date_start_precise === null) {
                        $date_start_precise = $minimal['date_start'];
                    }
                    if ($date_end_precise === null) {
                        $date_end_precise = $minimal['date_end'];
                    }
                }
            }
        } else {
            $minimal = $this->buildMinimalFallback($cruise);
            if ($minimal) {
                $waybill_rows = $minimal['waybill_rows'];
                $date_start_precise = $minimal['date_start'];
                $date_end_precise = $minimal['date_end'];
            }
        }

        if ($waybill_rows !== []) {
            $waybill_json = json_encode(array_map(function ($r) {
                return [
                    'town_name' => $r['town_name'],
                    'excursion' => $r['excursion'],
                    'bold' => $r['is_bold'],
                ];
            }, $waybill_rows), JSON_UNESCAPED_UNICODE);
        }

        return [
            'route' => $route,
            'date_start' => $date_start ? ($date_start . ' 00:00:00') : null,
            'date_end' => $date_stop ? ($date_stop . ' 00:00:00') : null,
            'date_start_precise' => $date_start_precise,
            'date_end_precise' => $date_end_precise,
            'days' => $days,
            'description' => $description,
            'schedule_html' => $schedule_html,
            'waybill_json' => $waybill_json,
            'waybill_rows' => $waybill_rows,
        ];
    }

    private function extractRouteFromName(string $name): ?string
    {
        if ($name === '') {
            return null;
        }
        $route = preg_replace('/\s*\([^)]+\)\s*/u', '', $name);
        return trim((string) $route) ?: null;
    }

    private function processScheduleData(array $routes, string $date_start): string
    {
        $days_of_week = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
        $lines = [];
        $lines[] = '<table><tbody>';
        $lines[] = '<tr><td>День</td><td>Стоянка</td><td>Программа дня</td></tr>';
        foreach ($routes as $route) {
            if (!isset($route['day'], $route['portName'])) {
                continue;
            }
            $date = Carbon::parse($date_start);
            $day = $route['day'];
            $port = $route['portName'];
            $excursion = $route['excursion'] ?? '';
            $time_start = $route['timeStart'] ?? '00:00:00';
            $time_stop = $route['timeStop'] ?? '00:00:00';
            $time = $this->formatScheduleTime($time_start, $time_stop);
            $ex_date = $date->copy()->addDays(intval($day) - 1);
            $day_of_week = $days_of_week[$ex_date->dayOfWeek];
            $ex_date_s = $ex_date->format('d.m.Y');
            $lines[] = "<tr><td>{$day} <br>{$ex_date_s}<br>{$time} ({$day_of_week})</td><td>{$port}</td><td>{$excursion}</td></tr>";
        }
        $lines[] = '</tbody></table>';
        return implode("\n", $lines);
    }

    private function formatScheduleTime(string $time_start, string $time_stop): string
    {
        if ($time_start === '00:00:00') {
            $time_stop_c = Carbon::parse($time_stop);
            return '<span class="ww_time">Отправление в ' . $time_stop_c->format('H:i') . '</span>';
        }
        if ($time_stop === '00:00:00') {
            $time_start_c = Carbon::parse($time_start);
            return '<span class="ww_time">Прибытие в ' . $time_start_c->format('H:i') . '</span>';
        }
        $time_start_c = Carbon::parse($time_start);
        $time_stop_c = Carbon::parse($time_stop);
        return '<span class="ww_time">' .
            $time_start_c->format('H:i') .
            ' - ' .
            $time_stop_c->format('H:i') .
            '</span>';
    }

    private function buildWaybillRowsFromRoutes(string $cruise_name, array $routes): array
    {
        $alt_routes = explode(' — ', $cruise_name);
        $alt_routes = array_map('trim', $alt_routes);
        $rows = [];
        $routes_end = count($routes) - 1;
        $routes_i = 0;
        foreach ($routes as $route) {
            if (!isset($route['portName'])) {
                continue;
            }
            $port = trim(strip_tags((string) $route['portName']));
            if ($port === '') {
                continue;
            }
            $day = $route['day'] ?? '';
            $excursion = $route['excursion'] ?? '';
            $excursion_text = $day ? "[ День: {$day} ] {$excursion}" : $excursion;
            $bold = (in_array($route['portName'], $alt_routes, true) || $routes_i === 0 || $routes_i === $routes_end) ? 1 : 0;
            $rows[] = [
                'town_name' => $port,
                'excursion' => $excursion_text,
                'is_bold' => $bold,
            ];
            $routes_i++;
        }
        return count($rows) >= 2 ? $rows : [];
    }

    private function processDates(array $routes, string $date_start, string $date_stop): array
    {
        if ($routes === []) {
            return [
                'date_start' => $date_start ? ($date_start . ' 00:00:00') : null,
                'date_end' => $date_stop ? ($date_stop . ' 00:00:00') : null,
            ];
        }
        $first_route = reset($routes);
        $last_route = end($routes);
        $date_start_precise = null;
        $date_end_precise = null;
        if ($first_route && isset($first_route['timeStop'])) {
            $date_start_precise = $date_start . ' ' . $first_route['timeStop'];
        }
        if ($last_route && isset($last_route['timeStart'])) {
            $date_end_precise = $date_stop . ' ' . $last_route['timeStart'];
        }
        return [
            'date_start' => $date_start_precise,
            'date_end' => $date_end_precise,
        ];
    }

    private function buildMinimalFallback(array $cruise): ?array
    {
        $name = (string) ($cruise['name'] ?? '');
        $date_start = $cruise['dateStart'] ?? null;
        $date_stop = $cruise['dateStop'] ?? null;
        if ($name === '' || !$date_start || !$date_stop) {
            return null;
        }
        $mini_route = explode(' — ', $name);
        $rows = [];
        foreach ($mini_route as $town_name) {
            if (str_contains($town_name, ')')) {
                $town_name = preg_replace('/\([^()]+\)/', '', $town_name);
            }
            $town_name = trim((string) $town_name);
            if ($town_name === '') {
                continue;
            }
            $rows[] = [
                'town_name' => $town_name,
                'excursion' => '',
                'is_bold' => 0,
            ];
        }
        if (count($rows) < 2) {
            return null;
        }
        $rows[0]['is_bold'] = 1;
        $rows[count($rows) - 1]['is_bold'] = 1;
        return [
            'waybill_rows' => $rows,
            'date_start' => $date_start . ' 00:00:00',
            'date_end' => $date_stop . ' 00:00:00',
        ];
    }

    private function persistPricesForCruise(int $cruise_id, int $ship_id, int $session_id, array $prices_data, string $now): void
    {
        $this->query('prices')->where('cruise_id', $cruise_id)->where('session_id', $session_id)->delete();

        $cabin_categories = [];
        $decks = [];
        $category_max_places = [];

        foreach ($prices_data['tariffs'] as $tariff) {
            $tariff_name = $tariff['tariff_name'] ?? '';
            $is_base = ($tariff_name === 'Тариф Взрослый' || $tariff_name === 'Тариф взрослый');
            $is_extended = ($tariff_name === 'Тариф Взрослый расширенный');
            if (!$is_base && !$is_extended) {
                continue;
            }
            if (!isset($tariff['prices']) || !is_array($tariff['prices'])) {
                continue;
            }
            foreach ($tariff['prices'] as $price) {
                $category_id = $price['rt_id'] ?? null;
                $deck_id = $price['deck_id'] ?? null;
                $places_qnt = (int) ($price['places_qnt'] ?? 1);
                if ($places_qnt <= 0) {
                    $places_qnt = 1;
                }
                if ($category_id !== null && !isset($cabin_categories[$category_id])) {
                    $cabin_categories[$category_id] = [
                        'id' => $category_id,
                        'name' => (string) ($price['rt_name'] ?? ''),
                        'description' => $price['rp_name'] ?? null,
                        'meta_id' => $price['rp_id'] ?? null,
                        'meta_name' => $price['rt_meta_name'] ?? null,
                        'ship_id' => $ship_id,
                        'deck_id' => null,
                    ];
                }
                if ($category_id !== null) {
                    $category_max_places[$category_id] = max((int) ($category_max_places[$category_id] ?? 1), $places_qnt);
                }
                if ($deck_id !== null && !isset($decks[$deck_id])) {
                    $decks[$deck_id] = [
                        'id' => $deck_id,
                        'name' => (string) ($price['deck_name'] ?? ''),
                        'meta_id' => $price['deck_meta_id'] ?? null,
                        'meta_name' => $price['deck_meta_name'] ?? null,
                        'ship_id' => $ship_id,
                    ];
                }
            }
        }

        foreach ($cabin_categories as $cid => &$cat) {
            $cat['places'] = (int) ($category_max_places[$cid] ?? 1);
        }
        unset($cat);

        foreach ($decks as $deck) {
            $this->query('decks')->updateOrInsert(
                ['id' => (int) $deck['id']],
                [
                    'id' => (int) $deck['id'],
                    'ship_id' => $deck['ship_id'],
                    'name' => $deck['name'],
                    'meta_id' => $deck['meta_id'],
                    'meta_name' => $deck['meta_name'],
                    'raw_json' => json_encode($deck, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
        foreach ($cabin_categories as $cat) {
            $this->query('cabin_categories')->updateOrInsert(
                ['id' => (int) $cat['id']],
                [
                    'id' => (int) $cat['id'],
                    'ship_id' => $cat['ship_id'],
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'places' => $cat['places'],
                    'deck_id' => $cat['deck_id'],
                    'meta_id' => $cat['meta_id'],
                    'meta_name' => $cat['meta_name'],
                    'raw_json' => json_encode($cat, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $prices_map = [];
        foreach ($prices_data['tariffs'] as $tariff) {
            $tariff_name = $tariff['tariff_name'] ?? '';
            $is_base = ($tariff_name === 'Тариф Взрослый' || $tariff_name === 'Тариф взрослый');
            $is_extended = ($tariff_name === 'Тариф Взрослый расширенный');
            if (!$is_base && !$is_extended) {
                continue;
            }
            if (!isset($tariff['prices']) || !is_array($tariff['prices'])) {
                continue;
            }
            foreach ($tariff['prices'] as $price) {
                $price_value = (int) ($price['price_value'] ?? 0);
                if ($price_value <= 0) {
                    continue;
                }
                $category_id = $price['rt_id'] ?? null;
                $deck_id = $price['deck_id'] ?? null;
                $places_qnt = (int) ($price['places_qnt'] ?? 1);
                if ($places_qnt <= 0) {
                    $places_qnt = 1;
                }
                if ($category_id === null) {
                    continue;
                }
                $key = $category_id . ':' . (int) ($deck_id ?? 0) . ':' . $places_qnt;
                if (!isset($prices_map[$key])) {
                    $prices_map[$key] = [
                        'cruise_id' => $cruise_id,
                        'cabin_category_id' => $category_id,
                        'deck_id' => $deck_id,
                        'price_value' => null,
                        'price_extra' => null,
                        'places_qnt' => $places_qnt,
                        'tariff_name' => 'Тариф Взрослый',
                        'raw_json' => json_encode($price, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($is_base) {
                    $prices_map[$key]['price_value'] = $price_value;
                } elseif ($is_extended) {
                    $prices_map[$key]['price_extra'] = $price_value;
                }
            }
        }

        foreach ($prices_map as $row) {
            if (empty($row['price_value'])) {
                continue;
            }
            $this->query('prices')->insert([
                'cruise_id' => $row['cruise_id'],
                'cabin_category_id' => $row['cabin_category_id'],
                'deck_id' => $row['deck_id'],
                'price_value' => $row['price_value'],
                'price_extra' => $row['price_extra'],
                'places_qnt' => $row['places_qnt'],
                'tariff_name' => $row['tariff_name'],
                'raw_json' => $row['raw_json'],
                'session_id' => $row['session_id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }
    }

    private function getWaterwayShipName(int $ship_id): ?string
    {
        $this->ensureWaterwayDb();
        $ship = $this->waterway_db->query('ships')->find($ship_id);
        if (!$ship) {
            return null;
        }
        $name = trim((string) ($ship->name ?? ''));
        return $name === '' ? null : $name;
    }

    private function getWaterwayCategory(int $ship_id, int $category_id): ?object
    {
        $this->ensureWaterwayDb();
        $cache_key = $ship_id . ':' . $category_id;
        if (array_key_exists($cache_key, $this->category_cache)) {
            return $this->category_cache[$cache_key];
        }
        $row = $this->waterway_db->query('cabin_categories')
            ->where('ship_id', $ship_id)
            ->where('id', $category_id)
            ->first();
        $this->category_cache[$cache_key] = $row ?: null;
        return $this->category_cache[$cache_key];
    }

    private function getApiClient(): WaterwayApiClient
    {
        if ($this->api_client === null) {
            $this->api_client = new WaterwayApiClient(30);
        }
        return $this->api_client;
    }

    private function ensureWaterwayDb(): void
    {
        if ($this->waterway_db === null) {
            $this->waterway_db = BasesApp::connect(self::BASE_CODE);
        }
    }

    private function query(string $table_name = 'records')
    {
        return BasesApp::connect(self::BASE_CODE)->query($table_name);
    }

    private function getActiveSessionId(): ?int
    {
        $session = $this->query('sessions')
            ->where('status', 'running')
            ->orderByDesc('id')
            ->first();
        return $session ? (int) $session->id : null;
    }

    private function getRequiredActiveSessionId(): int
    {
        $session_id = $this->getActiveSessionId();
        if ($session_id === null) {
            throw new Exception('No active parser session. Run processPrepareSession first.');
        }
        return $session_id;
    }

    private function closeRunningSessions(string $reason): void
    {
        $now = now()->toDateTimeString();
        $this->query('sessions')
            ->where('status', 'running')
            ->update([
                'status' => 'interrupted',
                'error_message' => $reason,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
