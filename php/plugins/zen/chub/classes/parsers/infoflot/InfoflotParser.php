<?php namespace Zen\Chub\Classes\Parsers\Infoflot;

use Carbon\Carbon;
use Exception;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Dto\CheckinDto;

class InfoflotParser
{
    private const string BASE_CODE = 'parser-db-infoflot';
    private const string API_BASE_URL = 'https://restapi.infoflot.com';
    private const string API_KEY = 'b5262f5d8de5be65b201bb5e3f5e544a245b6082';
    private const string CACHE_DIR = 'storage/parsers_cache/infoflot';

    private ?BasesApp $infoflot_db = null;
    private array $ships_cache = [];
    private array $categories_cache = [];
    private array $decks_cache = [];

    public static function make(): self
    {
        return new self();
    }

    public function importOrchestrator(): array
    {
        return [
            'parser-infoflot-prepare-session',
            'parser-infoflot-parse-ships',
            'parser-infoflot-parse-cruises-meta',
            'parser-infoflot-parse-cruise-cabins',
            'parser-infoflot-clean-cruises-without-prices',
            'parser-infoflot-finish-session',
            'infoflot-db-push',
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
                    // Stream иногда остается в "нейтральном" состоянии при пустых пакетах.
                    // В этом случае считаем дочерний шаг завершенным как no-op.
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

    public function processParseShipsData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseShipsData($session_id);
    }

    public function processCleanCruisesWithoutPrices(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->cleanCruisesWithoutPrices($session_id);
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

    public function createShipsBatches(): array
    {
        $session_id = $this->getRequiredActiveSessionId();
        $ships = $this->query('ships')
            ->where('session_id', $session_id)
            ->orderBy('id')
            ->get();

        $batches = [];
        foreach ($ships as $ship) {
            $batches[] = [
                'ship_id' => intval($ship->id),
            ];
        }

        if (empty($batches)) {
            return [['__noop' => true]];
        }

        return $batches;
    }

    public function handleShipCruisesBatch(?array $ship_item = null): int
    {
        if (empty($ship_item) || !empty($ship_item['__noop'])) {
            return 0;
        }
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCruisesMetaByShip($ship_item, $session_id);
    }

    public function createCruiseBatches(): array
    {
        $session_id = $this->getRequiredActiveSessionId();
        $cruises = $this->query('cruises')
            ->where('session_id', $session_id)
            ->orderBy('id')
            ->get();

        $batches = [];
        foreach ($cruises as $cruise) {
            $batches[] = [
                'cruise_id' => intval($cruise->id),
                'ship_id' => intval($cruise->ship_id ?? 0),
            ];
        }

        if (empty($batches)) {
            return [['__noop' => true]];
        }

        return $batches;
    }

    public function handleCruiseCabinsBatch(?array $cruise_item = null): int
    {
        if (empty($cruise_item) || !empty($cruise_item['__noop'])) {
            return 0;
        }
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCruiseCabins($cruise_item, $session_id);
    }

    public function startSession(): int
    {
        $this->closeRunningSessions('Interrupted by new parser run');
        $now = now()->toDateTimeString();
        return $this->query('sessions')->insertGetId([
            'source_name' => 'infoflot',
            'status' => 'running',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function finishSession(int $session_id): void
    {
        $this->query('sessions')
            ->where('id', $session_id)
            ->update([
                'status' => 'completed',
                'finished_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function markSessionError(int $session_id, string $error_message): void
    {
        $now = now()->toDateTimeString();
        $this->query('sessions')
            ->where('id', $session_id)
            ->update([
                'status' => 'error',
                'error_message' => mb_substr($error_message, 0, 2000),
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function parseShipsData(int $session_id): int
    {
        $page = 1;
        $limit = 100;
        $saved = 0;
        $now = now()->toDateTimeString();

        while (true) {
            $response = $this->getShipsDump($page, $limit, $session_id);
            $items = $this->normalizeCollection($response['data'] ?? []);
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $ship_id = intval($item['id'] ?? 0);
                if ($ship_id <= 0) {
                    continue;
                }

                if ($this->isMarineShip(
                    (string) ($item['name'] ?? ''),
                    (string) ($item['typeName'] ?? ''),
                    (string) ($item['operatorName'] ?? '')
                )) {
                    continue;
                }

                $this->query('ships')->updateOrInsert(
                    ['id' => $ship_id],
                    [
                        'name' => trim((string) ($item['name'] ?? '')),
                        'type_name' => trim((string) ($item['typeName'] ?? '')),
                        'operator_name' => trim((string) ($item['operatorName'] ?? '')),
                        'description' => trim((string) ($item['description'] ?? '')),
                        'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $saved++;
            }

            $next_page = $this->resolveNextPage($response);
            if ($next_page === null) {
                break;
            }

            $page = $next_page;
            if ($page > 300) {
                break;
            }
        }

        return $saved;
    }

    public function parseCruisesMetaByShip(array $ship_item, int $session_id): int
    {
        $ship_id = intval($ship_item['ship_id'] ?? 0);
        if ($ship_id <= 0) {
            return 0;
        }

        $page = 1;
        $limit = 500;
        $saved = 0;
        $now = now()->toDateTimeString();

        while (true) {
            $response = $this->getCruisesDumpByShip($ship_id, $page, $limit, $session_id);
            if (empty($response) || !is_array($response)) {
                break;
            }

            $items = $this->normalizeCollection($response['data'] ?? []);
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $cruise_id = intval($item['id'] ?? 0);
                if ($cruise_id <= 0) {
                    continue;
                }

                $date_start = trim((string) ($item['dateStart'] ?? ''));
                if ($date_start !== '') {
                    try {
                        if (Carbon::parse($date_start)->lt(Carbon::now()->startOfDay())) {
                            continue;
                        }
                    } catch (\Throwable $e) {
                    }
                }

                $route_name = trim((string) ($item['route'] ?? ''));
                $waybill = $this->buildWaybillFromRoute($route_name);
                $this->query('cruises')->updateOrInsert(
                    ['id' => $cruise_id],
                    [
                        'infoflot_cruise_id' => $cruise_id,
                        'ship_id' => $ship_id,
                        'name' => trim((string) ($item['name'] ?? '')),
                        'route_name' => $route_name,
                        'date_start' => $this->normalizeDateTime((string) ($item['dateStart'] ?? '')),
                        'date_end' => $this->normalizeDateTime((string) ($item['dateEnd'] ?? '')),
                        'days' => intval($item['days'] ?? 0) ?: null,
                        'nights' => intval($item['nights'] ?? 0) ?: null,
                        'description' => trim((string) ($item['description'] ?? '')),
                        'waybill_json' => json_encode($waybill, JSON_UNESCAPED_UNICODE),
                        'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $this->query('waybills')->where('cruise_id', $cruise_id)->delete();
                foreach ($waybill as $index => $point) {
                    $this->query('waybills')->insert([
                        'cruise_id' => $cruise_id,
                        'order_index' => $index,
                        'town_name' => (string) ($point['town_name'] ?? ''),
                        'arrival_at' => null,
                        'departure_at' => null,
                        'is_bold' => intval($point['is_bold'] ?? 0),
                        'raw_json' => json_encode($point, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                    ]);
                }

                $saved++;
            }

            $next_page = $this->resolveNextPage($response);
            if ($next_page === null) {
                break;
            }
            $page = $next_page;
            if ($page > 300) {
                break;
            }
        }

        return $saved;
    }

    public function parseCruiseCabins(array $cruise_item, int $session_id): int
    {
        $cruise_id = intval($cruise_item['cruise_id'] ?? 0);
        $ship_id = intval($cruise_item['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $ship_id <= 0) {
            return 0;
        }

        $dump = $this->getCruiseCabinsDump($cruise_id, $session_id);
        if ($dump === null || !is_array($dump)) {
            return 0;
        }

        $cabin_items = $this->normalizeCollection($dump['cabins'] ?? []);
        $type_to_deck = [];
        $type_to_places = [];
        $type_to_name = [];
        $now = now()->toDateTimeString();

        foreach ($cabin_items as $cabin_item) {
            if (!is_array($cabin_item)) {
                continue;
            }

            $type_id = intval($cabin_item['type_id'] ?? 0);
            if ($type_id <= 0) {
                continue;
            }

            $type_name = trim((string) ($cabin_item['type_name'] ?? $cabin_item['typeName'] ?? ''));
            $type_to_name[$type_id] = $type_name;

            $places_main = 1;
            if (isset($cabin_item['places']) && is_array($cabin_item['places'])) {
                $places_main = max(1, intval($cabin_item['places']['main'] ?? 1));
            }
            $type_to_places[$type_id] = $places_main;

            $deck_id = $this->resolveDeckIdFromCabin($cabin_item);
            if ($deck_id !== null) {
                $type_to_deck[$type_id] = $deck_id;
            }

            $this->query('cabin_categories')->updateOrInsert(
                ['id' => $type_id],
                [
                    'name' => $type_name,
                    'ship_id' => $ship_id,
                    'deck_id' => $deck_id,
                    'places_main_count' => $places_main,
                    'places_extra_count' => 0,
                    'raw_json' => json_encode($cabin_item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $prices_items = is_array($dump['prices'] ?? null) ? $dump['prices'] : [];
        $saved = 0;
        foreach ($prices_items as $type_id_raw => $price_item) {
            $type_id = intval($type_id_raw);
            if ($type_id <= 0 || !is_array($price_item)) {
                continue;
            }

            $price_adult = intval($price_item['prices']['main_bottom']['adult'] ?? 0);
            $price_default = intval($price_item['prices']['default'] ?? 0);
            if ($price_adult <= 0) {
                continue;
            }

            $type_name = trim((string) ($price_item['type_name'] ?? ($type_to_name[$type_id] ?? '')));
            $deck_id = intval($type_to_deck[$type_id] ?? 0) ?: null;
            $persons = max(1, intval($type_to_places[$type_id] ?? 1));

            if ($type_name !== '') {
                $this->query('cabin_categories')->updateOrInsert(
                    ['id' => $type_id],
                    [
                        'name' => $type_name,
                        'ship_id' => $ship_id,
                        'deck_id' => $deck_id,
                        'places_main_count' => $persons,
                        'places_extra_count' => 0,
                        'raw_json' => json_encode($price_item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $this->query('prices')->updateOrInsert(
                [
                    'cruise_id' => $cruise_id,
                    'cabin_category_id' => $type_id,
                    'session_id' => $session_id,
                ],
                [
                    'persons' => $persons,
                    'price_value' => $price_adult,
                    'price_default_value' => $price_default > 0 ? $price_default : null,
                    'deck_id' => $deck_id,
                    'is_available' => 1,
                    'raw_json' => json_encode($price_item, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    public function cleanCruisesWithoutPrices(int $session_id): int
    {
        $cruises = $this->query('cruises')
            ->where('session_id', $session_id)
            ->get();
        $deleted = 0;

        foreach ($cruises as $cruise) {
            $cruise_id = intval($cruise->id ?? 0);
            if ($cruise_id <= 0) {
                continue;
            }

            $has_prices = $this->query('prices')
                ->where('cruise_id', $cruise_id)
                ->where('session_id', $session_id)
                ->exists();
            if ($has_prices) {
                continue;
            }

            $this->query('waybills')->where('cruise_id', $cruise_id)->delete();
            $this->query('prices')->where('cruise_id', $cruise_id)->delete();
            $this->query('cruises')->where('id', $cruise_id)->delete();
            $deleted++;
        }

        return $deleted;
    }

    public function prodBatches(): array
    {
        $this->ensureInfoflotDb();

        $cruises = [];
        $this->infoflot_db->query('cruises')
            ->orderBy('id')
            ->chunk(100, function($records) use (&$cruises) {
                foreach ($records as $record) {
                    $cruises[] = [
                        'cruise_id' => intval($record->id),
                        'date_start' => (string) ($record->date_start ?? ''),
                        'date_end' => (string) ($record->date_end ?? ''),
                        'ship_id' => intval($record->ship_id ?? 0),
                    ];
                }
            });

        if (empty($cruises)) {
            return [['__noop' => true]];
        }

        return $cruises;
    }

    public function prodTransit(?array $cruise = null): void
    {
        if (empty($cruise) || !empty($cruise['__noop'])) {
            return;
        }
        $this->ensureInfoflotDb();

        $cruise_id = intval($cruise['cruise_id'] ?? 0);
        $ship_id = intval($cruise['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $ship_id <= 0) {
            return;
        }

        $ship_name = $this->getInfoflotShipName($ship_id);
        if ($ship_name === null || trim($ship_name) === '') {
            return;
        }

        $checkin_dto = CheckinDto::make(
            provider_code: 'infoflot',
            provider_checkin_id: $cruise_id,
            date_start: (string) ($cruise['date_start'] ?? ''),
            date_end: (string) ($cruise['date_end'] ?? ''),
        );
        $checkin_dto->resolveShip($ship_name, $ship_id);

        $waybill = $this->infoflot_db->query('waybills')
            ->where('cruise_id', $cruise_id)
            ->orderBy('order_index')
            ->get();
        foreach ($waybill as $point) {
            $checkin_dto->addWaybillPoint(
                town_name: (string) ($point->town_name ?? ''),
                is_important: intval($point->is_bold ?? 0) > 0,
            );
        }

        $prices = $this->infoflot_db->query('prices')
            ->where('cruise_id', $cruise_id)
            ->get();
        foreach ($prices as $price_record) {
            $category_id = intval($price_record->cabin_category_id ?? 0);
            if ($category_id <= 0) {
                continue;
            }

            $category = $this->getInfoflotCategory($ship_id, $category_id);
            if (!$category) {
                continue;
            }

            $deck = $this->getInfoflotDeck($price_record, $category);
            $places_main_qnt = max(1, intval($category->places_main_count ?? 1));
            $places_value = max(1, intval($price_record->persons ?? $places_main_qnt));
            $price_value = intval($price_record->price_value ?? 0);
            if ($price_value <= 0) {
                continue;
            }

            $checkin_dto->resolvePrice(
                provider_grade_name: (string) ($category->name ?? ('Category ' . $category_id)),
                provider_grade_uid: (string) $category_id,
                places_main_qnt: $places_main_qnt,
                places_extra_qnt: max(0, intval($category->places_extra_count ?? 0)),
                provider_deck_name: $deck?->name,
                provider_deck_uid: $deck ? (string) $deck->id : null,
                places_value: $places_value,
                price_value: $price_value,
                tariff_id: 1,
            );
        }

        $checkin_dto->push();
    }

    private function getShipsDump(int $page, int $limit, int $session_id): array
    {
        return $this->fetchJson(
            endpoint: '/ships',
            query: ['page' => $page, 'limit' => $limit, 'key' => $this->resolveApiKey()],
            timeout: 30,
            cache_file_name: 'ships/page-' . $page . '.json',
            session_id: $session_id
        );
    }

    private function getCruisesDumpByShip(int $ship_id, int $page, int $limit, int $session_id): array
    {
        try {
            return $this->fetchJson(
                endpoint: '/cruises',
                query: [
                    'key' => $this->resolveApiKey(),
                    'ship' => $ship_id,
                    'page' => $page,
                    'date' => date('Y-m-d'),
                    'limit' => $limit,
                ],
                timeout: 30,
                cache_file_name: 'cruises/ship-' . $ship_id . '-page-' . $page . '.json',
                session_id: $session_id
            );
        } catch (\Throwable $e) {
            // Для per-ship запроса это не должно валить весь batch-процесс.
            // Возвращаем "пустую страницу", чтобы процесс перешел к следующему судну.
            return $this->emptyPagedResponse();
        }
    }

    private function getCruiseCabinsDump(int $cruise_id, int $session_id): ?array
    {
        try {
            return $this->fetchJson(
                endpoint: '/cruises/' . $cruise_id . '/cabins',
                query: ['key' => $this->resolveApiKey()],
                timeout: 30,
                cache_file_name: 'cabins/' . $cruise_id . '.json',
                session_id: $session_id
            );
        } catch (\Throwable $e) {
            $this->trackCabinsResponse($cruise_id, $session_id, 'error', $e->getMessage());
            return null;
        }
    }

    private function fetchJson(
        string $endpoint,
        array $query = [],
        int $timeout = 30,
        ?string $cache_file_name = null,
        ?int $session_id = null
    ): array {
        $cache_file_name = $cache_file_name ?? (trim($endpoint, '/') . '.' . sha1(json_encode($query)) . '.json');
        $cache_file = base_path(self::CACHE_DIR . '/' . ltrim($cache_file_name, '/'));
        $cache_dir = dirname($cache_file);
        if (!is_dir($cache_dir) && !mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
            throw new Exception('Failed to create Infoflot cache directory: ' . $cache_dir);
        }

        if (file_exists($cache_file) && filesize($cache_file) > 0) {
            $cached = json_decode((string) file_get_contents($cache_file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = rtrim(self::API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $json = null;
        $decoded = null;
        $attempts = 3;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $json = $this->fetchRemoteFile($url, $timeout);
            if ($json === null || trim($json) === '') {
                if ($attempt < $attempts) {
                    sleep(1);
                    continue;
                }
                throw new Exception('Empty Infoflot payload: ' . $url);
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                if ($attempt < $attempts) {
                    sleep(1);
                    continue;
                }
                throw new Exception('Failed to decode Infoflot JSON: ' . $url);
            }

            break;
        }

        file_put_contents($cache_file, $json);
        if ($session_id !== null) {
            $this->query('sessions')
                ->where('id', $session_id)
                ->update([
                    'source_url' => $url,
                    'archive_path' => $cache_file,
                    'archive_hash' => sha1($json),
                    'archive_size' => strlen($json),
                    'updated_at' => now()->toDateTimeString(),
                ]);
        }

        return $decoded;
    }

    private function emptyPagedResponse(): array
    {
        return [
            'data' => [],
            'pagination' => [
                'pages' => [
                    'next' => null,
                ],
            ],
        ];
    }

    private function fetchRemoteFile(string $url, int $timeout): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; ChubInfoflotParser/1.0)',
                ],
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content !== false) {
            return $content;
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ChubInfoflotParser/1.0)');
        curl_setopt($ch, CURLOPT_RESOLVE, ['restapi.infoflot.com:443:178.248.239.118']);
        $content = curl_exec($ch);
        $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($content === false || !in_array($http_code, [200, 201], true)) {
            return null;
        }

        return (string) $content;
    }

    private function normalizeCollection(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }

        $is_assoc = array_keys($items) !== range(0, count($items) - 1);
        if ($is_assoc && !isset($items['id'])) {
            return array_values($items);
        }
        if ($is_assoc) {
            return [$items];
        }
        return array_values($items);
    }

    private function resolveNextPage(array $response): ?int
    {
        $next = $response['pagination']['pages']['next']['number'] ?? null;
        if ($next === null) {
            return null;
        }
        $next = intval($next);
        return $next > 0 ? $next : null;
    }

    private function isMarineShip(string $ship_name, string $ship_type, string $operator_name = ''): bool
    {
        $ship_name = trim($ship_name);
        $ship_type = trim($ship_type);
        $operator_name = trim($operator_name);

        $marine_types = ['лайнер', 'liner', 'cruise', 'круизный', 'ocean', 'морской'];
        foreach ($marine_types as $value) {
            if ($ship_type !== '' && mb_stripos($ship_type, $value) !== false) {
                return true;
            }
        }

        $marine_operators = ['MSC', 'Celebrity', 'Royal Caribbean', 'Costa', 'Norwegian', 'Princess', 'Holland America', 'Carnival'];
        foreach ($marine_operators as $value) {
            if ($operator_name !== '' && mb_stripos($operator_name, $value) !== false) {
                return true;
            }
        }

        $marine_ship_names = ['MSC', 'Celebrity', 'Royal Caribbean', 'Costa', 'Norwegian', 'Princess', 'Holland America', 'Carnival', 'AIDA', 'TUI', 'Marella'];
        foreach ($marine_ship_names as $value) {
            if ($ship_name !== '' && mb_stripos($ship_name, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    private function buildWaybillFromRoute(?string $route): array
    {
        $route = trim((string) $route);
        if ($route === '') {
            return [];
        }

        if (str_contains($route, ' — ')) {
            $parts = explode(' — ', $route);
        } elseif (str_contains($route, ' – ')) {
            $parts = explode(' – ', $route);
        } elseif (str_contains($route, ' - ')) {
            $parts = explode(' - ', $route);
        } else {
            $parts = preg_split('/\s[—–]\s/u', $route) ?: [];
        }

        $result = [];
        foreach ($parts as $item) {
            $town_name = trim((string) $item);
            $town_name = preg_replace('/\s*\([^)]+\)\s*/u', '', $town_name);
            $town_name = preg_replace('/,.*/u', '', $town_name);
            $town_name = trim((string) $town_name);
            if ($town_name === '') {
                continue;
            }

            $result[] = [
                'town_name' => $town_name,
                'is_bold' => 0,
            ];
        }

        if (count($result) >= 2) {
            $result[0]['is_bold'] = 1;
            $result[count($result) - 1]['is_bold'] = 1;
        }

        return $result;
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) === 10) {
            return $value . ' 00:00:00';
        }
        return $value;
    }

    private function resolveDeckIdFromCabin(array $cabin_item): ?int
    {
        $deck_id = intval($cabin_item['deck_id'] ?? 0);
        $deck_name = null;
        $position = null;

        if (isset($cabin_item['deck']) && is_string($cabin_item['deck'])) {
            $deck_name = trim($cabin_item['deck']);
        } elseif (isset($cabin_item['deck']) && is_array($cabin_item['deck'])) {
            $deck_name = trim((string) ($cabin_item['deck']['name'] ?? ''));
            if (!$deck_id) {
                $deck_id = intval($cabin_item['deck']['id'] ?? 0);
            }
            $position = intval($cabin_item['deck']['position'] ?? 0) ?: null;
        }

        if (!$deck_id && $deck_name !== null && $deck_name !== '') {
            $ship_id = intval($cabin_item['ship_id'] ?? 0);
            $deck_id = abs(crc32($deck_name . ':' . $ship_id)) % 1000000000;
        }
        if ($deck_id <= 0) {
            return null;
        }

        $this->query('decks')->updateOrInsert(
            ['id' => $deck_id],
            [
                'name' => $deck_name !== null && $deck_name !== '' ? $deck_name : ('Deck ' . $deck_id),
                'position' => $position,
                'raw_json' => json_encode($cabin_item['deck'] ?? [], JSON_UNESCAPED_UNICODE),
                'session_id' => $this->getRequiredActiveSessionId(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        return $deck_id;
    }

    private function getInfoflotShipName(int $ship_id): ?string
    {
        $this->ensureInfoflotDb();
        if (array_key_exists($ship_id, $this->ships_cache)) {
            return $this->ships_cache[$ship_id];
        }

        $ship = $this->infoflot_db->query('ships')->find($ship_id);
        if (!$ship) {
            $this->ships_cache[$ship_id] = null;
            return null;
        }

        $name = trim((string) ($ship->name ?? ''));
        $this->ships_cache[$ship_id] = $name === '' ? null : $name;
        return $this->ships_cache[$ship_id];
    }

    private function getInfoflotCategory(int $ship_id, int $category_id): ?object
    {
        $this->ensureInfoflotDb();
        $cache_key = $ship_id . ':' . $category_id;
        if (array_key_exists($cache_key, $this->categories_cache)) {
            return $this->categories_cache[$cache_key];
        }

        $category = $this->infoflot_db->query('cabin_categories')
            ->where('id', $category_id)
            ->where(function($q) use ($ship_id) {
                $q->where('ship_id', $ship_id)
                    ->orWhereNull('ship_id')
                    ->orWhere('ship_id', 0);
            })
            ->orderByDesc('ship_id')
            ->first();

        $this->categories_cache[$cache_key] = $category ?: null;
        return $this->categories_cache[$cache_key];
    }

    private function getInfoflotDeck(object $price_record, object $category): ?object
    {
        $this->ensureInfoflotDb();
        $deck_id = intval($price_record->deck_id ?? 0);
        if ($deck_id <= 0) {
            $deck_id = intval($category->deck_id ?? 0);
        }
        if ($deck_id <= 0) {
            return null;
        }

        if (array_key_exists($deck_id, $this->decks_cache)) {
            return $this->decks_cache[$deck_id];
        }

        $deck = $this->infoflot_db->query('decks')->find($deck_id);
        $this->decks_cache[$deck_id] = $deck ?: null;
        return $this->decks_cache[$deck_id];
    }

    private function trackCabinsResponse(int $cruise_id, int $session_id, string $status, string $payload): void
    {
        $this->query('cabin_responses')->updateOrInsert(
            ['infoflot_cruise_id' => $cruise_id, 'session_id' => $session_id],
            [
                'status' => $status,
                'raw_payload' => mb_substr($payload, 0, 200000),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );
    }

    private function resolveApiKey(): string
    {
        return trim((string) (env('CHUB_INFOFLOT_API_KEY') ?: env('INFOFLOT_API_KEY') ?: self::API_KEY));
    }

    private function getActiveSessionId(): ?int
    {
        $session = $this->query('sessions')
            ->where('status', 'running')
            ->orderByDesc('id')
            ->first();
        if (!$session) {
            return null;
        }
        return intval($session->id);
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

    private function ensureInfoflotDb(): void
    {
        if ($this->infoflot_db === null) {
            $this->infoflot_db = BasesApp::connect(self::BASE_CODE);
        }
    }

    private function query(string $table_name = 'records')
    {
        return BasesApp::connect(self::BASE_CODE)->query($table_name);
    }
}
