<?php namespace Zen\Chub\Classes\Parsers\Germes;

use Exception;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Dto\CheckinDto;

class GermesParser
{
    private const string BASE_CODE = 'parser-db-germes';
    private const string API_BASE_URL = 'https://river.sputnik-germes.ru/XML/';
    private const string CACHE_DIR = 'storage/parsers_cache/germes';

    private ?BasesApp $germes_db = null;
    private array $germes_ships_cache = [];
    private array $germes_cabin_categories_cache = [];
    private array $germes_decks_cache = [];
    private ?array $cabin_to_category_cache = null;

    public static function make(): self
    {
        return new self();
    }

    public function importOrchestrator(): array
    {
        return [
            'parser-germes-prepare-session',
            'parser-germes-parse-ships',
            'parser-germes-parse-cabin-categories',
            'parser-germes-parse-cabins',
            'parser-germes-parse-cruises-meta',
            'parser-germes-parse-cruise-details',
            'parser-germes-sync-category-ships',
            'parser-germes-clean-cruises-without-prices',
            'parser-germes-finish-session',
            'germes-db-push',
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

    public function processPrepareSession(): int
    {
        return $this->startSession();
    }

    public function processParseShipsData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseShipsData($session_id);
    }

    public function processParseCabinCategoriesData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCabinCategoriesData($session_id);
    }

    public function processParseCabinsData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCabinsData($session_id);
    }

    public function processParseCruisesMetaData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCruisesMetaData($session_id);
    }

    public function processSyncCabinCategoriesShipIds(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->syncCabinCategoriesShipIds($session_id);
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

    public function createCruiseBatches(): array
    {
        $session_id = $this->getRequiredActiveSessionId();
        $rows = $this->query('cruises')
            ->where('session_id', $session_id)
            ->orderBy('id')
            ->get();

        $batches = [];
        foreach ($rows as $row) {
            $batches[] = [
                'cruise_id' => intval($row->id),
                'ship_id' => intval($row->ship_id ?? 0),
            ];
        }

        return $batches;
    }

    public function handleCruiseBatch(array $cruise_item): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCruiseDetails($cruise_item, $session_id);
    }

    public function startSession(): int
    {
        $this->closeRunningSessions('Interrupted by new parser run');
        $now = now()->toDateTimeString();
        return $this->query('sessions')->insertGetId([
            'source_name' => 'germes',
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
        $dump = $this->getShipsDump($session_id);
        $items = $this->normalizeCollection($dump['Теплоход'] ?? []);
        $saved = 0;
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $ship_id = intval($data['id'] ?? 0);
            if ($ship_id <= 0) {
                continue;
            }

            $this->query('ships')->updateOrInsert(
                ['id' => $ship_id],
                [
                    'name' => trim((string) ($data['Название'] ?? '')),
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    public function parseCabinCategoriesData(int $session_id): int
    {
        $dump = $this->getCabinCategoriesDump($session_id);
        $items = $this->normalizeCollection($dump['Класс'] ?? []);
        $saved = 0;
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $category_id = intval($data['id'] ?? 0);
            if ($category_id <= 0) {
                continue;
            }

            $description = $this->normalizeDescription($item['Описание'] ?? null);
            $deck_name = $this->extractDeckNameFromDescription($description);
            $deck_id = $deck_name !== null ? $this->resolveDeckIdByName($deck_name, $session_id) : null;

            $this->query('cabin_categories')->updateOrInsert(
                ['id' => $category_id],
                [
                    'name' => trim((string) ($item['Название'] ?? $data['Название'] ?? '')),
                    'ship_id' => intval($data['id_teplohod'] ?? 0) ?: null,
                    'deck_id' => $deck_id,
                    'places_main_count' => 1,
                    'places_extra_count' => 0,
                    'description' => $description,
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    public function parseCabinsData(int $session_id): int
    {
        $dump = $this->getCabinsDump($session_id);
        $items = $this->normalizeCollection($dump['Kauta'] ?? []);
        $saved = 0;
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $cabin_id = intval($data['id'] ?? 0);
            $category_id = intval($data['idClassKauta'] ?? 0);
            if ($cabin_id <= 0 || $category_id <= 0) {
                continue;
            }

            $this->query('cabins')->updateOrInsert(
                ['cabin_id' => $cabin_id],
                [
                    'cabin_category_id' => $category_id,
                    'number' => intval($data['number'] ?? 0) ?: null,
                    'ship_id' => null,
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    public function parseCruisesMetaData(int $session_id): int
    {
        $dump = $this->getCruisesDump($session_id);
        $items = $this->normalizeCollection($dump['тур'] ?? []);
        $saved = 0;
        $now = now()->toDateTimeString();

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? [];
            $cruise_id = intval($data['id'] ?? 0);
            $ship_id = intval($item['Теплоход'] ?? 0);
            if ($cruise_id <= 0 || $ship_id <= 0) {
                continue;
            }

            $date_start = $this->normalizeDateTime(
                (string) ($item['ДатаОтплытия'] ?? ''),
                (string) ($item['ВремяОтплытия'] ?? '00:00')
            );
            $date_end = $this->normalizeDateTime(
                (string) ($item['ДатаПрибытия'] ?? ''),
                (string) ($item['ВремяПрибытия'] ?? '00:00')
            );

            $route_name = trim(strip_tags((string) ($item['Маршрут'] ?? '')));
            $this->query('cruises')->updateOrInsert(
                ['id' => $cruise_id],
                [
                    'germes_cruise_id' => $cruise_id,
                    'ship_id' => $ship_id,
                    'name' => trim((string) ($item['Название'] ?? '')),
                    'route_name' => $route_name,
                    'date_start' => $date_start,
                    'date_end' => $date_end,
                    'waybill_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    public function parseCruiseDetails(array $cruise_item, int $session_id): int
    {
        $cruise_id = intval($cruise_item['cruise_id'] ?? 0);
        if ($cruise_id <= 0) {
            return 0;
        }

        $cruise_row = $this->query('cruises')->where('id', $cruise_id)->first();
        if (!$cruise_row) {
            return 0;
        }

        $saved_prices = 0;
        $waybill = $this->buildWaybillFromTraceDump($this->getTraceDump($cruise_id, $session_id));
        if (!empty($waybill)) {
            $this->query('waybills')->where('cruise_id', $cruise_id)->delete();
            foreach ($waybill as $index => $point) {
                $this->query('waybills')->insert([
                    'cruise_id' => $cruise_id,
                    'order_index' => $index,
                    'town_name' => (string) ($point['town_name'] ?? ''),
                    'arrival_at' => !empty($point['arrival_at']) ? (string) $point['arrival_at'] : null,
                    'departure_at' => !empty($point['departure_at']) ? (string) $point['departure_at'] : null,
                    'is_bold' => intval($point['is_bold'] ?? 0),
                    'raw_json' => json_encode($point, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => now()->toDateTimeString(),
                ]);
            }

            $this->query('cruises')->where('id', $cruise_id)->update([
                'waybill_json' => json_encode($waybill, JSON_UNESCAPED_UNICODE),
                'updated_at' => now()->toDateTimeString(),
            ]);
        }

        $prices_dump = $this->getPricesDump($cruise_id, $session_id);
        $price_items = $this->normalizeCollection($prices_dump['Каюта'] ?? []);
        $now = now()->toDateTimeString();
        foreach ($price_items as $price_item) {
            $price_data = $price_item['@attributes'] ?? $price_item;
            $cabin_id = intval($price_data['id'] ?? $price_item['id'] ?? 0);
            $price_value = intval($price_data['ЦенаОснМест'] ?? $price_item['ЦенаОснМест'] ?? 0);
            if ($cabin_id <= 0 || $price_value <= 0) {
                continue;
            }

            $cabin_category_id = $this->resolveCabinCategoryId($cabin_id);
            if ($cabin_category_id <= 0) {
                continue;
            }

            $this->query('prices')->updateOrInsert(
                [
                    'cruise_id' => $cruise_id,
                    'cabin_id' => $cabin_id,
                    'cabin_category_id' => $cabin_category_id,
                    'session_id' => $session_id,
                ],
                [
                    'persons' => 1,
                    'price_value' => $price_value,
                    'is_available' => 1,
                    'raw_json' => json_encode($price_item, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $saved_prices++;
        }

        return $saved_prices;
    }

    public function syncCabinCategoriesShipIds(int $session_id): int
    {
        $cruises = $this->query('cruises')
            ->where('session_id', $session_id)
            ->get();
        $ship_by_cruise = [];
        foreach ($cruises as $cruise) {
            $ship_by_cruise[intval($cruise->id)] = intval($cruise->ship_id ?? 0);
        }

        $updated = 0;
        $prices = $this->query('prices')
            ->where('session_id', $session_id)
            ->get();
        foreach ($prices as $price) {
            $category_id = intval($price->cabin_category_id ?? 0);
            $cruise_id = intval($price->cruise_id ?? 0);
            $ship_id = intval($ship_by_cruise[$cruise_id] ?? 0);
            if ($category_id <= 0 || $ship_id <= 0) {
                continue;
            }

            $affected = $this->query('cabin_categories')
                ->where('id', $category_id)
                ->where(function($q) {
                    $q->whereNull('ship_id')->orWhere('ship_id', 0);
                })
                ->update([
                    'ship_id' => $ship_id,
                    'updated_at' => now()->toDateTimeString(),
                ]);
            $updated += intval($affected);
        }

        return $updated;
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
        $this->ensureGermesDb();

        $cruises = [];
        $this->germes_db->query('cruises')
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

        return $cruises;
    }

    public function prodTransit(array $cruise): void
    {
        $this->ensureGermesDb();

        $cruise_id = intval($cruise['cruise_id'] ?? 0);
        $ship_id = intval($cruise['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $ship_id <= 0) {
            return;
        }

        $ship_name = $this->getGermesShipName($ship_id);
        if ($ship_name === null || trim($ship_name) === '') {
            return;
        }

        $checkin_dto = CheckinDto::make(
            provider_code: 'germes',
            provider_checkin_id: $cruise_id,
            date_start: (string) ($cruise['date_start'] ?? ''),
            date_end: (string) ($cruise['date_end'] ?? ''),
        );
        $checkin_dto->resolveShip($ship_name, $ship_id);

        $waybill_rows = $this->germes_db->query('waybills')
            ->where('cruise_id', $cruise_id)
            ->orderBy('order_index')
            ->get();
        foreach ($waybill_rows as $point) {
            $checkin_dto->addWaybillPoint(
                town_name: (string) ($point->town_name ?? ''),
                is_important: intval($point->is_bold ?? 0) > 0,
                point_type: null,
                arrival_at: !empty($point->arrival_at) ? (string) $point->arrival_at : null,
                departure_at: !empty($point->departure_at) ? (string) $point->departure_at : null,
            );
        }

        $prices = $this->germes_db->query('prices')
            ->where('cruise_id', $cruise_id)
            ->get();
        foreach ($prices as $price_record) {
            $category_id = intval($price_record->cabin_category_id ?? 0);
            if ($category_id <= 0) {
                continue;
            }

            $germes_category = $this->getGermesCategory($ship_id, $category_id);
            if (!$germes_category) {
                continue;
            }

            $germes_deck = $this->getGermesDeck($germes_category);
            $places_main_qnt = max(1, intval($germes_category->places_main_count ?? 1));
            $places_value = max(1, intval($price_record->persons ?? $places_main_qnt));
            $price_value = intval($price_record->price_value ?? 0);
            if ($price_value <= 0) {
                continue;
            }

            $checkin_dto->resolvePrice(
                provider_grade_name: (string) ($germes_category->name ?? ('Category ' . $category_id)),
                provider_grade_uid: (string) $category_id,
                places_main_qnt: $places_main_qnt,
                places_extra_qnt: max(0, intval($germes_category->places_extra_count ?? 0)),
                provider_deck_name: $germes_deck?->name,
                provider_deck_uid: $germes_deck ? (string) $germes_deck->id : null,
                places_value: $places_value,
                price_value: $price_value,
                tariff_id: 1,
            );
        }

        $checkin_dto->push();
    }

    private function getGermesShipName(int $ship_id): ?string
    {
        $this->ensureGermesDb();

        if (array_key_exists($ship_id, $this->germes_ships_cache)) {
            return $this->germes_ships_cache[$ship_id];
        }

        $ship = $this->germes_db->query('ships')->find($ship_id);
        if (!$ship) {
            $this->germes_ships_cache[$ship_id] = null;
            return null;
        }

        $name = trim((string) ($ship->name ?? ''));
        $this->germes_ships_cache[$ship_id] = $name === '' ? null : $name;
        return $this->germes_ships_cache[$ship_id];
    }

    private function getGermesCategory(int $ship_id, int $category_id): ?object
    {
        $this->ensureGermesDb();

        $cache_key = $ship_id . ':' . $category_id;
        if (array_key_exists($cache_key, $this->germes_cabin_categories_cache)) {
            return $this->germes_cabin_categories_cache[$cache_key];
        }

        $category = $this->germes_db->query('cabin_categories')
            ->where('id', $category_id)
            ->where(function($q) use ($ship_id) {
                $q->where('ship_id', $ship_id)
                    ->orWhereNull('ship_id')
                    ->orWhere('ship_id', 0);
            })
            ->orderByDesc('ship_id')
            ->first();

        $this->germes_cabin_categories_cache[$cache_key] = $category ?: null;
        return $this->germes_cabin_categories_cache[$cache_key];
    }

    private function getGermesDeck(?object $category): ?object
    {
        $this->ensureGermesDb();
        $deck_id = intval($category->deck_id ?? 0);
        if ($deck_id <= 0) {
            return null;
        }

        if (array_key_exists($deck_id, $this->germes_decks_cache)) {
            return $this->germes_decks_cache[$deck_id];
        }

        $deck = $this->germes_db->query('decks')->find($deck_id);
        $this->germes_decks_cache[$deck_id] = $deck ?: null;
        return $this->germes_decks_cache[$deck_id];
    }

    private function getShipsDump(int $session_id): array
    {
        return $this->fetchDump('ListTeplohod.php', [], 30, 'ships.xml', $session_id);
    }

    private function getCabinCategoriesDump(int $session_id): array
    {
        return $this->fetchDump('ListClassKauta.php', [], 30, 'cabin_categories.xml', $session_id);
    }

    private function getCabinsDump(int $session_id): array
    {
        return $this->fetchDump('ListKauta.php', [], 30, 'cabins.xml', $session_id);
    }

    private function getCruisesDump(int $session_id): array
    {
        return $this->fetchDump('exportTur.php', [], 30, 'cruises.xml', $session_id);
    }

    private function getTraceDump(int $cruise_id, int $session_id): ?array
    {
        try {
            return $this->fetchDump(
                'exportTrace.php',
                ['tur' => $cruise_id],
                20,
                'trace/' . $cruise_id . '.xml',
                $session_id
            );
        } catch (\Throwable $e) {
            $this->trackTraceResponse($cruise_id, $session_id, 'error', $e->getMessage());
            return null;
        }
    }

    private function getPricesDump(int $cruise_id, int $session_id): ?array
    {
        try {
            return $this->fetchDump(
                'exportKauta.php',
                ['tur' => $cruise_id],
                20,
                'prices/' . $cruise_id . '.xml',
                $session_id
            );
        } catch (\Throwable $e) {
            $this->trackPriceResponse($cruise_id, $session_id, 'error', $e->getMessage());
            return null;
        }
    }

    private function fetchDump(
        string $endpoint,
        array $query = [],
        int $timeout = 30,
        ?string $cache_file_name = null,
        ?int $session_id = null
    ): array {
        $xml = $this->fetchXml($endpoint, $query, $timeout, $cache_file_name, $session_id);
        return $this->xmlToArray($xml);
    }

    private function fetchXml(
        string $endpoint,
        array $query = [],
        int $timeout = 30,
        ?string $cache_file_name = null,
        ?int $session_id = null
    ): string {
        $cache_file_name = $cache_file_name ?? ($endpoint . '.' . sha1(json_encode($query)) . '.xml');
        $cache_file = base_path(self::CACHE_DIR . '/' . ltrim($cache_file_name, '/'));
        $cache_dir = dirname($cache_file);
        if (!is_dir($cache_dir) && !mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
            throw new Exception('Failed to create Germes cache directory: ' . $cache_dir);
        }

        if (file_exists($cache_file) && filesize($cache_file) > 0) {
            $cached = (string) file_get_contents($cache_file);
            if ($cached !== '') {
                return $cached;
            }
        }

        $url = rtrim(self::API_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $xml = $this->fetchRemoteFile($url, $timeout);
        if ($xml === null || trim($xml) === '') {
            throw new Exception('Empty Germes payload: ' . $url);
        }

        file_put_contents($cache_file, $xml);
        if ($session_id !== null) {
            $this->query('sessions')
                ->where('id', $session_id)
                ->update([
                    'source_url' => $url,
                    'archive_path' => $cache_file,
                    'archive_hash' => sha1($xml),
                    'archive_size' => strlen($xml),
                    'updated_at' => now()->toDateTimeString(),
                ]);
        }

        return $xml;
    }

    private function fetchRemoteFile(string $url, int $timeout): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; ChubGermesParser/1.0)',
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ChubGermesParser/1.0)');
        $content = curl_exec($ch);
        $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($content === false || $http_code !== 200) {
            return null;
        }

        return (string) $content;
    }

    private function xmlToArray(string $xml_content): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(
            $xml_content,
            'SimpleXMLElement',
            LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT
        );
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = 'Failed to parse Germes XML';
            if ($errors) {
                $message .= ': ' . trim($errors[0]->message);
            }
            throw new Exception($message);
        }

        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        $result = json_decode((string) $json, true);
        if (!is_array($result)) {
            throw new Exception('Failed to convert Germes XML to array');
        }

        return $result;
    }

    private function normalizeCollection(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            return [];
        }
        if (array_key_exists('@attributes', $items)) {
            return [$items];
        }
        return array_values($items);
    }

    private function normalizeDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $value = join('', array_map(function($item) {
                return is_scalar($item) ? (string) $item : '';
            }, $value));
        }
        $result = trim((string) $value);
        return $result === '' ? null : $result;
    }

    private function normalizeDateTime(string $date, string $time): string
    {
        $date = trim($date);
        $time = trim($time);
        $parts = explode('.', $date);
        if (count($parts) === 3) {
            $date = sprintf('%04d-%02d-%02d', intval($parts[2]), intval($parts[1]), intval($parts[0]));
        }

        if ($time === '') {
            $time = '00:00';
        }
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        return trim($date . ' ' . $time);
    }

    private function buildWaybillFromTraceDump(?array $trace_dump): array
    {
        if ($trace_dump === null) {
            return [];
        }

        $towns = $this->normalizeCollection($trace_dump['Tour']['City'] ?? []);
        $result = [];
        $last_index = max(0, count($towns) - 1);

        foreach ($towns as $index => $town) {
            $town_name = trim(strip_tags((string) $town));
            if ($town_name === '') {
                continue;
            }

            $result[] = [
                'town_name' => $town_name,
                'arrival_at' => null,
                'departure_at' => null,
                'is_bold' => ($index === 0 || $index === $last_index) ? 1 : 0,
            ];
        }

        return $result;
    }

    private function extractDeckNameFromDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $text = mb_strtolower($description);
        $patterns = [
            'нижн' => 'Нижняя палуба',
            'главн' => 'Главная палуба',
            'средн' => 'Средняя палуба',
            'шлюп' => 'Шлюпочная палуба',
            'солнечн' => 'Солнечная палуба',
            'прогулочн' => 'Прогулочная палуба',
            'верхн' => 'Верхняя палуба',
            'багажн' => 'Багажная палуба',
        ];

        foreach ($patterns as $prefix => $deck_name) {
            $prefix_pos = mb_strpos($text, $prefix);
            $deck_word_pos = mb_strpos($text, 'палуб', $prefix_pos ?: 0);
            if ($prefix_pos !== false && $deck_word_pos !== false && $deck_word_pos > $prefix_pos) {
                return $deck_name;
            }
        }

        return null;
    }

    private function resolveDeckIdByName(string $deck_name, int $session_id): int
    {
        $deck_name = trim($deck_name);
        if ($deck_name === '') {
            throw new Exception('Deck name can not be empty');
        }

        $deck_id = abs(hexdec(substr(md5($deck_name), 0, 8)));
        $this->query('decks')->updateOrInsert(
            ['id' => $deck_id],
            [
                'name' => $deck_name,
                'raw_json' => json_encode(['name' => $deck_name], JSON_UNESCAPED_UNICODE),
                'session_id' => $session_id,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        return $deck_id;
    }

    private function resolveCabinCategoryId(int $cabin_id): int
    {
        if ($this->cabin_to_category_cache === null) {
            $this->cabin_to_category_cache = [];
            $rows = $this->query('cabins')->get();
            foreach ($rows as $row) {
                $this->cabin_to_category_cache[intval($row->cabin_id)] = intval($row->cabin_category_id ?? 0);
            }
        }

        return intval($this->cabin_to_category_cache[$cabin_id] ?? 0);
    }

    private function trackTraceResponse(int $cruise_id, int $session_id, string $status, string $payload): void
    {
        $this->query('trace_responses')->updateOrInsert(
            ['germes_cruise_id' => $cruise_id, 'session_id' => $session_id],
            [
                'status' => $status,
                'raw_payload' => mb_substr($payload, 0, 200000),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );
    }

    private function trackPriceResponse(int $cruise_id, int $session_id, string $status, string $payload): void
    {
        $this->query('price_responses')->updateOrInsert(
            ['germes_cruise_id' => $cruise_id, 'session_id' => $session_id],
            [
                'status' => $status,
                'raw_payload' => mb_substr($payload, 0, 200000),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );
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

    private function ensureGermesDb(): void
    {
        if ($this->germes_db === null) {
            $this->germes_db = BasesApp::connect(self::BASE_CODE);
        }
    }

    private function query(string $table_name = 'records')
    {
        return BasesApp::connect(self::BASE_CODE)->query($table_name);
    }
}
