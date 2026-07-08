<?php namespace Zen\Chub\Classes\Parsers\Volga;

use Exception;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Dto\CheckinDto;

class VolgaParser
{
    private const string BASE_CODE = 'parser-db-volga';
    private const string DEFAULT_XML_URL = 'https://test.volgawolga.ru/xml/daily2026.xml';
    private const string XML_CACHE_PATH = 'storage/parsers_cache/volga/volga_next_url.xml';
    private BasesApp $volga_db;
    private array $volga_cabin_categories_cache = []; // Кэш для метода getVolgaCategory()
    private array $volga_decks_cache = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Оркестратор дочерних stream-процессов для flow parser-volga.
     */
    public function importOrchestrator(): array
    {
        return [
            'parser-volga-prepare-session',
            'parser-volga-download-xml',
            'parser-volga-parse-ships',
            'parser-volga-parse-decks',
            'parser-volga-parse-cabin-categories',
            'parser-volga-parse-cabins',
            'parser-volga-parse-cruises',
            'parser-volga-parse-prices',
            'parser-volga-parse-free',
            'parser-volga-clean-cruises-without-prices',
            'parser-volga-finish-session',
            'volga-db-push',
        ];
    }

    /**
     * Обработчик оркестратора: запускает дочерний процесс и ждёт завершения.
     */
    public function run(string|array|null $value = null): void
    {
        $process_code = is_array($value) ? (string) ($value['code'] ?? '') : (string) $value;
        $process_code = trim($process_code);
        if ($process_code === '') {
            return;
        }

        ProcessApp::make()->runScheduleProcess($process_code);
        while (true) {
            sleep(1);
            $stream = Stream::connect($process_code);
            if ($stream->isCompleted() || $stream->isError() || $stream->isStopped()) {
                break;
            }
        }
    }

    public function processPrepareSession(): int
    {
        return $this->startSession();
    }

    public function processDownloadXml(): string
    {
        return $this->downloadXmlFile();
    }

    public function processParseShips(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseShips($this->getXmlDump(), $session_id);
    }

    public function processParseDecks(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseDecks($this->getXmlDump(), $session_id);
    }

    public function processParseCabinCategories(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseCabinCategories($this->getXmlDump(), $session_id);
    }

    public function processParseCabins(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseCabins($this->getXmlDump(), $session_id);
    }

    public function processParsePrices(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parsePrices($this->getXmlDump(), $session_id);
    }

    public function processParseFree(): int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseFree($this->getXmlDump(), $session_id);
    }

    public function processCleanCruisesWithoutPrices(): int
    {
        return $this->cleanCruisesWithoutPrices();
    }

    public function processFinishSession(): ?int
    {
        $session_id = $this->getActiveSessionId();
        if ($session_id === null) {
            return null;
        }

        $this->finishSession($session_id);
        return $session_id;
    }

    /**
     * Подготовка batch-пакетов по круизам.
     * Flow hook source: Zen.Chub.Classes.Parsers.Volga.VolgaParser.createCruiseBatches
     */
    public function createCruiseBatches(): array
    {
        $dump = $this->getXmlDump();
        return $this->normalizeCollection($dump['cruises']['cruise'] ?? []);
    }

    /**
     * Обработка одного cruise-пакета.
     * Flow hook handler: Zen.Chub.Classes.Parsers.Volga.VolgaParser.handleCruiseBatch
     */
    public function handleCruiseBatch(array $cruise_item): ?int
    {
        $session_id = $this->getOrCreateActiveSessionId();
        return $this->parseCruise($cruise_item, $session_id);
    }

    public function startSession(): int
    {
        $now = now()->toDateTimeString();
        return $this->query('sessions')->insertGetId([
            'source_url' => self::DEFAULT_XML_URL,
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

    public function downloadXmlFile(?string $xml_url = null, bool $force = false, int $timeout = 30): string
    {
        $xml_url = trim((string) ($xml_url ?: self::DEFAULT_XML_URL));
        if ($xml_url === '') {
            throw new Exception('URL источника Volga не задан');
        }

        $xml_path = base_path(self::XML_CACHE_PATH);
        $xml_dir = dirname($xml_path);
        if (!is_dir($xml_dir) && !mkdir($xml_dir, 0775, true) && !is_dir($xml_dir)) {
            throw new Exception('Не удалось создать директорию кеша Volga: ' . $xml_dir);
        }

        if (!$force && file_exists($xml_path) && filesize($xml_path) > 0) {
            return $xml_path;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; ChubVolgaParser/1.0)',
                ],
            ],
        ]);

        $xml_content = @file_get_contents($xml_url, false, $context);
        if ($xml_content === false && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $xml_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ChubVolgaParser/1.0)');

            $xml_content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($xml_content === false || $http_code !== 200) {
                throw new Exception("Ошибка скачивания Volga XML. HTTP={$http_code}; CURL={$curl_error}");
            }
        }

        if ($xml_content === false || trim($xml_content) === '') {
            throw new Exception('Получен пустой XML контент Volga');
        }

        if (file_put_contents($xml_path, $xml_content) === false) {
            throw new Exception('Не удалось сохранить XML кеш Volga: ' . $xml_path);
        }

        $this->updateLastSessionMeta($xml_path, $xml_url, $xml_content);
        return $xml_path;
    }

    public function getXmlDump(?string $xml_path = null): array
    {
        $xml_path = $xml_path ?: base_path(self::XML_CACHE_PATH);
        if (!file_exists($xml_path)) {
            throw new Exception('XML кеш Volga не найден: ' . $xml_path);
        }

        $xml_content = file_get_contents($xml_path);
        if ($xml_content === false || trim($xml_content) === '') {
            throw new Exception('XML файл Volga пуст');
        }

        $dump = $this->xmlToArray($xml_content);
        if (!isset($dump['cruises']['cruise'])) {
            throw new Exception('В XML Volga отсутствует раздел cruises.cruise');
        }

        return $dump;
    }

    public function parseShips(array $dump, int $session_id): int
    {
        $items = $this->normalizeCollection($dump['ships']['ship'] ?? []);
        $inserted = 0;

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $ship_id = intval($data['id'] ?? 0);
            if ($ship_id <= 0) {
                continue;
            }

            $this->query('ships')->updateOrInsert(
                ['id' => $ship_id],
                [
                    'name' => (string) ($data['name'] ?? ''),
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function parseDecks(array $dump, int $session_id): int
    {
        $items = $this->normalizeCollection($dump['decks']['deck'] ?? []);
        $inserted = 0;

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $deck_id = intval($data['id'] ?? 0);
            if ($deck_id <= 0) {
                continue;
            }

            $this->query('decks')->updateOrInsert(
                ['id' => $deck_id],
                [
                    'name' => (string) ($data['name'] ?? ''),
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function parseCabinCategories(array $dump, int $session_id): int
    {
        $items = $this->normalizeCollection($dump['classes']['class'] ?? []);
        $inserted = 0;

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $category_id = intval($data['id'] ?? 0);
            if ($category_id <= 0) {
                continue;
            }

            $this->query('cabin_categories')->updateOrInsert(
                ['id' => $category_id],
                [
                    'name' => (string) ($data['name'] ?? ''),
                    'comment' => (string) ($data['comment'] ?? ''),
                    'places_main_count' => intval($data['m_count'] ?? 0),
                    'places_extra_count' => intval($data['r_count'] ?? 0),
                    'no_full' => intval($data['no_full'] ?? 0),
                    'deck_id' => isset($data['deck']) ? intval($data['deck']) : null,
                    'ship_id' => isset($data['ship_id']) ? intval($data['ship_id']) : null,
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function parseCabins(array $dump, int $session_id): int
    {
        $items = $this->normalizeCollection($dump['cabins']['cabin'] ?? []);
        $inserted = 0;

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $cabin_id = intval($data['id'] ?? 0);
            if ($cabin_id <= 0) {
                continue;
            }

            $this->query('cabins')->updateOrInsert(
                ['id' => $cabin_id],
                [
                    'class_id' => intval($data['class_id'] ?? 0),
                    'deck_id' => intval($data['deck'] ?? 0),
                    'ship_id' => $this->resolveCabinShipId($data),
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'updated_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function parseCruise(array $cruise_item, int $session_id): ?int
    {
        $data = $cruise_item['@attributes'] ?? $cruise_item;
        $cruise_id = intval($data['id'] ?? 0);
        if ($cruise_id <= 0) {
            return null;
        }

        $date_start = $this->buildDateTime(
            (string) ($data['begin_date'] ?? ''),
            (string) ($data['begin_time'] ?? '')
        );
        $date_end = $this->buildDateTime(
            (string) ($data['end_date'] ?? ''),
            (string) ($data['end_time'] ?? '')
        );

        $waybill_points = $this->buildWaybillFromRoute((string) ($data['route'] ?? ''));

        $this->query('cruises')->updateOrInsert(
            ['id' => $cruise_id],
            [
                'ship_id' => intval($data['ship_id'] ?? 0),
                'name' => (string) ($data['name'] ?? ''),
                'route' => (string) ($data['route'] ?? ''),
                'begin_date' => (string) ($data['begin_date'] ?? ''),
                'begin_time' => (string) ($data['begin_time'] ?? ''),
                'end_date' => (string) ($data['end_date'] ?? ''),
                'end_time' => (string) ($data['end_time'] ?? ''),
                'date_start' => $date_start,
                'date_end' => $date_end,
                'waybill_json' => json_encode($waybill_points, JSON_UNESCAPED_UNICODE),
                'raw_json' => json_encode($cruise_item, JSON_UNESCAPED_UNICODE),
                'session_id' => $session_id,
                'updated_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
            ]
        );

        $this->query('waybills')->where('cruise_id', $cruise_id)->delete();
        foreach ($waybill_points as $point) {
            $this->query('waybills')->insert([
                'cruise_id' => $cruise_id,
                'town_name' => (string) ($point['town_name'] ?? ''),
                'town_name_raw' => (string) ($point['town_name_raw'] ?? ''),
                'town_id' => $point['town_id'] ?? null,
                'order_index' => intval($point['order_index'] ?? 0),
                'bold' => intval($point['bold'] ?? 0),
                'excursion' => (string) ($point['excursion'] ?? ''),
                'raw_json' => json_encode($point, JSON_UNESCAPED_UNICODE),
                'session_id' => $session_id,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        return $cruise_id;
    }

    public function parsePrices(array $dump, int $session_id): int
    {
        $spo_map = $this->buildSpoMap($dump);
        $items = $this->normalizeCollection($dump['prices']['price'] ?? []);
        $inserted = 0;

        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;

            $cruise_id = intval($data['cruise_id'] ?? 0);
            $class_id = intval($data['class_id'] ?? 0);
            $price_value = intval($data['price'] ?? 0);
            $nofull = intval($data['nofull'] ?? 0);

            if ($cruise_id <= 0 || $class_id <= 0 || $price_value <= 0) {
                continue;
            }

            $this->query('prices')->updateOrInsert(
                [
                    'cruise_id' => $cruise_id,
                    'cabin_category_id' => $class_id,
                    'session_id' => $session_id,
                ],
                [
                    'price_value' => $price_value,
                    'price2_value' => $spo_map[$cruise_id . ':' . $class_id] ?? null,
                    'nofull' => $nofull,
                    'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                    'created_at' => now()->toDateTimeString(),
                ]
            );
            $inserted++;
        }

        return $inserted;
    }

    public function parseFree(array $dump, int $session_id): int
    {
        $cruise_items = $this->normalizeCollection($dump['free']['cruise'] ?? []);
        $inserted = 0;

        foreach ($cruise_items as $cruise_item) {
            $cruise_data = $cruise_item['@attributes'] ?? $cruise_item;
            $cruise_id = intval($cruise_data['id'] ?? 0);
            if ($cruise_id <= 0) {
                continue;
            }

            $free_cabins = $this->normalizeCollection($cruise_item['cabin'] ?? []);
            foreach ($free_cabins as $free_cabin_item) {
                $free_cabin_data = $free_cabin_item['@attributes'] ?? $free_cabin_item;
                $cabin_id = intval($free_cabin_data['id'] ?? 0);
                if ($cabin_id <= 0) {
                    continue;
                }

                $this->query('free')->updateOrInsert(
                    [
                        'cruise_id' => $cruise_id,
                        'cabin_id' => $cabin_id,
                        'session_id' => $session_id,
                    ],
                    [
                        'free_value' => intval($free_cabin_data['free'] ?? 0),
                        'raw_json' => json_encode([
                            'cruise' => $cruise_data,
                            'cabin' => $free_cabin_item,
                        ], JSON_UNESCAPED_UNICODE),
                        'created_at' => now()->toDateTimeString(),
                    ]
                );
                $inserted++;
            }
        }

        return $inserted;
    }

    public function cleanCruisesWithoutPrices(): int
    {
        $cruises = $this->query('cruises')->get();
        $deleted = 0;

        foreach ($cruises as $cruise) {
            $price_exists = $this->query('prices')
                ->where('cruise_id', $cruise->id)
                ->exists();

            if ($price_exists) {
                continue;
            }

            $this->query('waybills')->where('cruise_id', $cruise->id)->delete();
            $this->query('cruises')->where('id', $cruise->id)->delete();
            $deleted++;
        }

        return $deleted;
    }

    private function updateLastSessionMeta(string $xml_path, string $xml_url, string $xml_content): void
    {
        $session_id = $this->getLastSessionId();
        if ($session_id === null) {
            return;
        }

        $this->query('sessions')
            ->where('id', $session_id)
            ->update([
                'source_url' => $xml_url,
                'xml_path' => $xml_path,
                'xml_hash' => sha1($xml_content),
                'xml_size' => strlen($xml_content),
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    private function getLastSessionId(): ?int
    {
        $session = $this->query('sessions')->orderByDesc('id')->first();
        if (!$session) {
            return null;
        }
        return intval($session->id);
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

    private function getOrCreateActiveSessionId(): int
    {
        return $this->getActiveSessionId() ?? $this->startSession();
    }

    private function buildSpoMap(array $dump): array
    {
        $result = [];
        $items = $this->normalizeCollection($dump['spos']['spo'] ?? []);
        foreach ($items as $item) {
            $data = $item['@attributes'] ?? $item;
            $cruise_id = intval($data['cruise_id'] ?? 0);
            $class_id = intval($data['class_id'] ?? 0);
            if ($cruise_id <= 0 || $class_id <= 0) {
                continue;
            }

            $result[$cruise_id . ':' . $class_id] = intval($data['spo'] ?? 0);

            $session_id = $this->getLastSessionId();
            if ($session_id !== null) {
                $this->query('spos')->updateOrInsert(
                    [
                        'cruise_id' => $cruise_id,
                        'cabin_category_id' => $class_id,
                        'session_id' => $session_id,
                    ],
                    [
                        'spo_value' => intval($data['spo'] ?? 0),
                        'raw_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                        'created_at' => now()->toDateTimeString(),
                    ]
                );
            }
        }
        return $result;
    }

    private function resolveCabinShipId(array $cabin_data): ?int
    {
        if (isset($cabin_data['ship_id']) && intval($cabin_data['ship_id']) > 0) {
            return intval($cabin_data['ship_id']);
        }

        if (isset($cabin_data['ship']) && intval($cabin_data['ship']) > 0) {
            return intval($cabin_data['ship']);
        }

        return null;
    }

    private function buildWaybillFromRoute(string $route): array
    {
        $route = trim($route);
        if ($route === '') {
            return [];
        }

        $normalized = str_replace(['—', '–', '⏹'], '-', $route);
        $normalized = preg_replace('/\s*-\s*/u', ' - ', (string) $normalized);
        $normalized = preg_replace('/\s{2,}/u', ' ', (string) $normalized);
        $parts = explode(' - ', (string) $normalized);

        $waybill = [];
        foreach ($parts as $index => $part) {
            $town_name_raw = trim($part);
            if ($town_name_raw === '') {
                continue;
            }

            $waybill[] = [
                'order_index' => $index,
                'town_name_raw' => $town_name_raw,
                'town_name' => $this->cleanTownName($town_name_raw),
                'town_id' => null,
                'excursion' => '',
                'bold' => 0,
            ];
        }

        return $waybill;
    }

    private function cleanTownName(string $route): string
    {
        $clean = str_replace('⏴', '(', $route);
        $clean = str_replace('⏵', ')', $clean);
        $clean = preg_replace('/\([^)]*\)/u', '', $clean);
        $clean = str_replace(['«', '»', '"', "'"], '', (string) $clean);
        $clean = preg_replace('/\s*\+\s*/u', ' ', (string) $clean);
        $clean = preg_replace('/\s{2,}/u', ' ', (string) $clean);
        return trim((string) $clean);
    }

    private function buildDateTime(string $date, string $time): ?string
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp) . ' ' . $time;
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

    private function query(string $table_name = 'records')
    {
        return BasesApp::connect(self::BASE_CODE)->query($table_name);
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
            $message = 'Ошибка парсинга XML Volga';
            if ($errors) {
                $message .= ': ' . trim($errors[0]->message);
            }
            throw new Exception($message);
        }

        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        $result = json_decode((string) $json, true);
        if (!is_array($result)) {
            throw new Exception('Ошибка преобразования XML Volga в массив');
        }

        return $result;
    }

    private function getVolgaDeck(int $class_id): ?object
    {
        $this->ensureVolgaDb();

        $decks = $this->volga_db->query('cabins')->where('class_id', $class_id)->get();
        $decks_ids = $decks->pluck('deck_id')->unique()->values()->toArray();
        $has_multiple_decks = count($decks_ids) > 1 ? true : false;

        if (!$has_multiple_decks) {
            $deck_id = $decks_ids[0];
            return $this->volga_decks_cache[$deck_id] ?? 0
                ? $this->volga_decks_cache[$deck_id]
                : $this->volga_decks_cache[$deck_id] = $this->volga_db->query('decks')->find($deck_id);
        }

        return null;
    }

    private function getVolgaShip(int $volga_ship_id)
    {
        $this->ensureVolgaDb();

        $ship = $this->volga_db->query('ships')->find($volga_ship_id);
        return $ship->name;
    }

    private function getVolgaCategory(
        int $volga_cabin_category_id
    ) {
        $this->ensureVolgaDb();

        if ($this->volga_cabin_categories_cache[$volga_cabin_category_id] ?? false) {
            return $this->volga_cabin_categories_cache[$volga_cabin_category_id];
        }

        $category = $this->volga_db->query('cabin_categories')
            ->where('id', $volga_cabin_category_id)
            ->first();
        
        return $category;
    }

    private function ensureVolgaDb(): void
    {
        if (!isset($this->volga_db)) {
            $this->volga_db = BasesApp::connect('parser-db-volga');
        }
    }

    # Подготовка пакетов для обработки
    public function prodBatches()
    {
        $cruises = [];
        BasesApp::connect('parser-db-volga')
            ->query('cruises')
            ->orderBy('id')
            ->chunk(100, function($records) use (&$cruises) {
                foreach ($records as $record) {
                    $cruises[] = [
                        'cruise_id' => $record->id,
                        'date_start' => $record->date_start,
                        'date_end' => $record->date_end,
                        'ship_id' => $record->ship_id,
                    ];  
                }
            });
        return $cruises;
    }

    # Метод добавления данных в базу через DTO
    public function prodTransit(array $cruise)
    {
        $this->ensureVolgaDb();
        
        $checkin_dto = CheckinDto::make(
            provider_code: 'volga',
            provider_checkin_id: $cruise['cruise_id'],
            date_start: $cruise['date_start'],
            date_end: $cruise['date_end'],
        );
        $ship_name = $this->getVolgaShip($cruise['ship_id']);
        $checkin_dto->resolveShip($ship_name, $cruise['ship_id']);

        $waybill = $this->volga_db->query('waybills')->where('cruise_id', $cruise['cruise_id'])->get();

        # Собираем маршрут
        foreach ($waybill as $point) {
            $checkin_dto->addWaybillPoint($point->town_name);
        }

        # Добавляем цены категории кают, палубы
        $prices = $this->volga_db->query('prices')->where('cruise_id', $cruise['cruise_id'])->get();
        foreach ($prices as $price_record) {
            $volga_cabin_category = $this->getVolgaCategory($price_record->cabin_category_id);
            $volga_deck = $this->getVolgaDeck($price_record->cabin_category_id);
            $checkin_dto->resolvePrice(
                provider_grade_name: $volga_cabin_category->name,
                provider_grade_uid: $volga_cabin_category->id,
                places_main_qnt: $volga_cabin_category->places_main_count,
                places_extra_qnt: $volga_cabin_category->places_extra_count,
                provider_deck_name: $volga_deck->name ?? null,
                provider_deck_uid: $volga_deck->id ?? null,
                places_value: $volga_cabin_category->places_main_count,
                price_value: $price_record->price_value,
                tariff_id: 1,
            );
        }
        
        # Добавляем в базу данных
        $checkin_dto->push();
    }
}