<?php namespace Zen\Chub\Classes\Parsers\Gama;

use Exception;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Classes\System\Stream;
use Zen\Chub\Dto\CheckinDto;

class GamaParser
{
    private const string BASE_CODE = 'parser-db-gama';
    private const string API_KEY = 'gIOZhOWvGDa177aLNh0rofIO';
    private const string ARCHIVE_URL = 'https://gama-nn.ru/satellite/xml/zip/?key=';
    private const string ROUTE_URL = 'https://gama-nn.ru/satellite/route/';
    private const string ARCHIVE_FILE_PATH = 'storage/parsers_cache/gama/gama.zip';
    private const string ARCHIVE_EXTRACT_DIR = 'storage/parsers_cache/gama/archive';
    private ?BasesApp $gama_db = null;
    private array $gama_cabin_categories_cache = [];
    private array $gama_decks_cache = [];

    public static function make(): self
    {
        return new self();
    }

    public function importOrchestrator(): array
    {
        return [
            'parser-gama-prepare-session',
            'parser-gama-download-archive',
            'parser-gama-parse-navigation',
            'parser-gama-parse-ships',
            'parser-gama-parse-cruises',
            'parser-gama-clean-cruises-without-prices',
            'parser-gama-finish-session',
            'gama-db-push',
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

    public function processDownloadArchive(): string
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->downloadArchive(false, 40, $session_id);
    }

    public function processParseNavigationData(): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseNavigationData($session_id);
    }

    public function processParseShipsData(): array
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
                'gama_cruise_id' => intval($row->gama_cruise_id),
                'ship_id' => intval($row->ship_id),
            ];
        }

        return $batches;
    }

    public function handleCruiseBatch(array $cruise_item): int
    {
        $session_id = $this->getRequiredActiveSessionId();
        return $this->parseCruisePrices($cruise_item, $session_id);
    }

    public function startSession(): int
    {
        $this->closeRunningSessions('Interrupted by new parser run');
        $now = now()->toDateTimeString();
        return $this->query('sessions')->insertGetId([
            'source_name' => 'gama',
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

    public function downloadArchive(bool $force = false, int $timeout = 40, ?int $session_id = null): string
    {
        $archive_path = base_path(self::ARCHIVE_FILE_PATH);
        $extract_dir = base_path(self::ARCHIVE_EXTRACT_DIR);

        $archive_dir = dirname($archive_path);
        if (!is_dir($archive_dir) && !mkdir($archive_dir, 0775, true) && !is_dir($archive_dir)) {
            throw new Exception('Failed to create Gama archive directory: ' . $archive_dir);
        }
        if (!is_dir($extract_dir) && !mkdir($extract_dir, 0775, true) && !is_dir($extract_dir)) {
            throw new Exception('Failed to create Gama extract directory: ' . $extract_dir);
        }

        $required_files = [
            $extract_dir . '/navigation.xml',
            $extract_dir . '/dir_generic.xml',
        ];
        $is_cached = file_exists($archive_path) && filesize($archive_path) > 0;
        foreach ($required_files as $required_file) {
            $is_cached = $is_cached && file_exists($required_file) && filesize($required_file) > 0;
        }
        $archive_url = self::ARCHIVE_URL . $this->resolveApiKey();
        if (!$force && $is_cached) {
            $meta_session_id = $session_id ?? $this->getActiveSessionId();
            if ($meta_session_id !== null) {
                $this->updateSessionArchiveMeta(
                    $meta_session_id,
                    $archive_path,
                    $archive_url,
                    sha1_file($archive_path) ?: null,
                    file_exists($archive_path) ? filesize($archive_path) : null
                );
            }
            return $extract_dir;
        }

        $archive_content = $this->fetchRemoteFile($archive_url, $timeout);
        if ($archive_content === null || trim($archive_content) === '') {
            throw new Exception('Empty Gama archive content');
        }
        if (file_put_contents($archive_path, $archive_content) === false) {
            throw new Exception('Failed to save Gama archive file: ' . $archive_path);
        }

        $this->extractArchive($archive_path, $extract_dir);
        $meta_session_id = $session_id ?? $this->getActiveSessionId() ?? $this->getLastSessionId();
        if ($meta_session_id !== null) {
            $this->updateSessionArchiveMeta(
                $meta_session_id,
                $archive_path,
                $archive_url,
                sha1($archive_content),
                strlen($archive_content)
            );
        }
        return $extract_dir;
    }

    public function parseNavigationData(int $session_id): int
    {
        $dump = $this->getNavigationDump();
        $navigations = $this->normalizeCollection($dump['NavigationList']['Navigation'] ?? []);
        $inserted_cruises = 0;
        $now = now()->toDateTimeString();

        foreach ($navigations as $navigation_item) {
            $navigation_data = $navigation_item['@attributes'] ?? [];
            $navigation_id = intval($navigation_data['id'] ?? 0);
            $ship_id = intval($navigation_data['ship_id'] ?? 0);
            $ship_name = (string) ($navigation_data['ship_name'] ?? '');

            if ($navigation_id > 0) {
                $this->query('navigations')->updateOrInsert(
                    ['id' => $navigation_id],
                    [
                        'ship_id' => $ship_id > 0 ? $ship_id : null,
                        'ship_name' => $ship_name,
                        'raw_json' => json_encode($navigation_item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            if ($ship_id > 0) {
                $this->query('ships')->updateOrInsert(
                    ['id' => $ship_id],
                    [
                        'name' => $ship_name,
                        'raw_json' => json_encode($navigation_data, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $path_list = $this->normalizeCollection($navigation_item['PathList']['Path'] ?? []);
            $route_list = $this->normalizeCollection($navigation_item['RouteList']['Route'] ?? []);

            foreach ($route_list as $route_item) {
                $route_data = $route_item['@attributes'] ?? [];
                $gama_cruise_id = intval($route_data['id'] ?? 0);
                if ($gama_cruise_id <= 0) {
                    continue;
                }

                $path_start_id = intval($route_data['path_s_id'] ?? 0);
                $path_finish_id = intval($route_data['path_f_id'] ?? 0);
                $waybill = $this->buildWaybillByPathRange($path_list, $path_start_id, $path_finish_id);

                $this->query('cruises')->updateOrInsert(
                    ['id' => $gama_cruise_id],
                    [
                        'gama_cruise_id' => $gama_cruise_id,
                        'navigation_id' => $navigation_id > 0 ? $navigation_id : null,
                        'ship_id' => $ship_id > 0 ? $ship_id : null,
                        'name' => (string) ($route_data['name'] ?? ''),
                        'route_name' => (string) ($route_data['name'] ?? ''),
                        'date_start' => (string) ($route_data['s'] ?? ''),
                        'date_end' => (string) ($route_data['f'] ?? ''),
                        'path_start_id' => $path_start_id > 0 ? $path_start_id : null,
                        'path_finish_id' => $path_finish_id > 0 ? $path_finish_id : null,
                        'waybill_json' => json_encode($waybill, JSON_UNESCAPED_UNICODE),
                        'raw_json' => json_encode($route_item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $this->query('waybills')->where('cruise_id', $gama_cruise_id)->delete();
                foreach ($waybill as $index => $point) {
                    $this->query('waybills')->insert([
                        'cruise_id' => $gama_cruise_id,
                        'order_index' => $index,
                        'town_name' => (string) ($point['town_name'] ?? ''),
                        'arrival_at' => (string) ($point['arrival_at'] ?? ''),
                        'departure_at' => (string) ($point['departure_at'] ?? ''),
                        'raw_json' => json_encode($point, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                    ]);
                }

                $inserted_cruises++;
            }
        }

        return $inserted_cruises;
    }

    public function parseShipsData(int $session_id): array
    {
        $dump = $this->getGenericDump();
        $ships = $this->normalizeCollection($dump['ShipList']['Ship'] ?? []);
        $categories = $this->normalizeCollection($dump['CategoryList']['Category'] ?? []);
        $categories_map = $this->buildCategoriesMap($categories);
        $now = now()->toDateTimeString();

        $decks_count = 0;
        $categories_count = 0;
        $cabins_count = 0;

        foreach ($ships as $ship_item) {
            $ship_data = $ship_item['@attributes'] ?? [];
            $ship_id = intval($ship_data['id'] ?? 0);
            if ($ship_id <= 0) {
                continue;
            }

            $this->query('ships')->updateOrInsert(
                ['id' => $ship_id],
                [
                    'name' => (string) ($ship_data['name'] ?? ''),
                    'raw_json' => json_encode($ship_item, JSON_UNESCAPED_UNICODE),
                    'session_id' => $session_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $decks = $this->normalizeCollection($ship_item['DeckList']['Deck'] ?? []);
            foreach ($decks as $deck_item) {
                $deck_data = $deck_item['@attributes'] ?? [];
                $deck_id = intval($deck_data['id'] ?? 0);
                if ($deck_id <= 0) {
                    continue;
                }

                $this->query('decks')->updateOrInsert(
                    ['id' => $deck_id],
                    [
                        'ship_id' => $ship_id,
                        'name' => (string) ($deck_data['name'] ?? ''),
                        'raw_json' => json_encode($deck_item, JSON_UNESCAPED_UNICODE),
                        'session_id' => $session_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $decks_count++;

                $cabins = $this->normalizeCollection($deck_item['CabinList']['Cabin'] ?? []);
                foreach ($cabins as $cabin_item) {
                    $cabin_data = $cabin_item['@attributes'] ?? [];
                    $cabin_id = intval($cabin_data['id'] ?? 0);
                    $category_id = intval($cabin_data['category_id'] ?? 0);
                    $places = intval($cabin_data['places'] ?? 0);
                    if ($cabin_id <= 0 || $category_id <= 0) {
                        continue;
                    }

                    $category_name = (string) ($categories_map[$category_id]['name'] ?? '');
                    $this->query('cabin_categories')->updateOrInsert(
                        ['category_id' => $category_id, 'ship_id' => $ship_id],
                        [
                            'category_id' => $category_id,
                            'name' => $category_name,
                            'places' => $places > 0 ? $places : null,
                            'deck_id' => $deck_id,
                            'raw_json' => json_encode($categories_map[$category_id] ?? [], JSON_UNESCAPED_UNICODE),
                            'session_id' => $session_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                    $categories_count++;

                    $this->query('cabins')->updateOrInsert(
                        ['cabin_id' => $cabin_id, 'ship_id' => $ship_id],
                        [
                            'cabin_id' => $cabin_id,
                            'cabin_category_id' => $category_id,
                            'deck_id' => $deck_id,
                            'places' => $places > 0 ? $places : null,
                            'raw_json' => json_encode($cabin_item, JSON_UNESCAPED_UNICODE),
                            'session_id' => $session_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                    $cabins_count++;
                }
            }
        }

        return [
            'ships' => count($ships),
            'decks' => $decks_count,
            'cabin_categories' => $categories_count,
            'cabins' => $cabins_count,
        ];
    }

    public function parseCruiseBatches(int $session_id): int
    {
        $items = $this->createCruiseBatches();
        $prices_count = 0;
        foreach ($items as $item) {
            $prices_count += $this->parseCruisePrices($item, $session_id);
        }
        return $prices_count;
    }

    public function parseCruisePrices(array $cruise_item, int $session_id): int
    {
        $cruise_id = intval($cruise_item['cruise_id'] ?? 0);
        $gama_cruise_id = intval($cruise_item['gama_cruise_id'] ?? 0);
        $ship_id = intval($cruise_item['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $gama_cruise_id <= 0) {
            return 0;
        }

        $route_dump = $this->getRouteDump($gama_cruise_id, $session_id);
        if ($route_dump === null || !isset($route_dump['Route']['CabinList']['Cabin'])) {
            return 0;
        }

        $cabin_map = $this->buildCabinMapForShip($ship_id);
        $cabins = $this->normalizeCollection($route_dump['Route']['CabinList']['Cabin']);
        $saved = 0;
        $now = now()->toDateTimeString();

        foreach ($cabins as $cabin_item) {
            $cabin_data = $cabin_item['@attributes'] ?? [];
            $cabin_id = intval($cabin_data['id'] ?? 0);
            $is_available = intval($cabin_data['available'] ?? 0) > 0;
            if (!$is_available || $cabin_id <= 0) {
                continue;
            }

            $resolved_category_id = intval($cabin_data['category_id'] ?? 0);
            $resolved_places = intval($cabin_data['places'] ?? 0);
            if (isset($cabin_map[$cabin_id])) {
                $resolved_category_id = intval($cabin_map[$cabin_id]['category_id'] ?? $resolved_category_id);
                $resolved_places = intval($cabin_map[$cabin_id]['places'] ?? $resolved_places);
            }
            if ($resolved_category_id <= 0) {
                continue;
            }

            $costs = $this->normalizeCollection($cabin_item['Cost'] ?? []);
            foreach ($costs as $cost_item) {
                $cost_data = $cost_item['@attributes'] ?? [];
                $persons = intval($cost_data['persons'] ?? 0);
                $price_value = intval($cost_data['std_3'] ?? 0);
                $price_child = intval($cost_data['child_3'] ?? 0);

                if ($price_value <= 0 || $persons <= 0) {
                    continue;
                }
                if ($resolved_places > 0 && $persons !== $resolved_places) {
                    continue;
                }

                $this->query('prices')->updateOrInsert(
                    [
                        'cruise_id' => $cruise_id,
                        'cabin_category_id' => $resolved_category_id,
                        'persons' => $persons,
                        'session_id' => $session_id,
                    ],
                    [
                        'cabin_id' => $cabin_id,
                        'price_value' => $price_value,
                        'price_child_value' => $price_child > 0 ? $price_child : null,
                        'is_available' => 1,
                        'raw_json' => json_encode([
                            'cabin' => $cabin_item,
                            'cost' => $cost_item,
                        ], JSON_UNESCAPED_UNICODE),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $saved++;
            }
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
            $has_prices = $this->query('prices')
                ->where('cruise_id', intval($cruise->id))
                ->exists();

            if ($has_prices) {
                continue;
            }

            $this->query('waybills')->where('cruise_id', intval($cruise->id))->delete();
            $this->query('cruises')->where('id', intval($cruise->id))->delete();
            $deleted++;
        }

        return $deleted;
    }

    private function getNavigationDump(): array
    {
        $xml_path = $this->getExtractFilePath('navigation.xml');
        return $this->xmlToArray((string) file_get_contents($xml_path));
    }

    private function getGenericDump(): array
    {
        $xml_path = $this->getExtractFilePath('dir_generic.xml');
        return $this->xmlToArray((string) file_get_contents($xml_path));
    }

    private function getRouteDump(int $gama_cruise_id, int $session_id): ?array
    {
        $routes_dir = base_path(self::ARCHIVE_EXTRACT_DIR . '/routes');
        if (!is_dir($routes_dir) && !mkdir($routes_dir, 0775, true) && !is_dir($routes_dir)) {
            throw new Exception('Failed to create Gama routes cache directory: ' . $routes_dir);
        }

        $route_cache_file = $routes_dir . '/' . $gama_cruise_id . '.xml';
        if (!file_exists($route_cache_file) || filesize($route_cache_file) <= 0) {
            $url = self::ROUTE_URL . $gama_cruise_id . '/?key=' . $this->resolveApiKey();
            $content = $this->fetchRemoteFile($url, 20);
            if ($content === null) {
                return null;
            }
            file_put_contents($route_cache_file, $content);
        } else {
            $content = (string) file_get_contents($route_cache_file);
        }

        if (str_contains($content, 'Sub expired')) {
            $this->trackRouteResponse($gama_cruise_id, $session_id, 'expired', $content);
            return null;
        }

        try {
            $dump = $this->xmlToArray($content);
            $this->trackRouteResponse($gama_cruise_id, $session_id, 'ok', $content);
            return $dump;
        } catch (\Throwable $e) {
            $this->trackRouteResponse($gama_cruise_id, $session_id, 'invalid_xml', mb_substr($content, 0, 4000));
            return null;
        }
    }

    private function fetchRemoteFile(string $url, int $timeout): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; ChubGamaParser/1.0)',
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ChubGamaParser/1.0)');
        $content = curl_exec($ch);
        $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($content === false || $http_code !== 200) {
            return null;
        }

        return (string) $content;
    }

    private function extractArchive(string $archive_path, string $extract_dir): void
    {
        if (!class_exists('\ZipArchive')) {
            throw new Exception('ZipArchive extension is required for Gama parser');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($archive_path);
        if ($opened !== true) {
            throw new Exception('Failed to open Gama ZIP archive');
        }

        $zip->extractTo($extract_dir);
        $zip->close();

        $required_files = [
            $extract_dir . '/navigation.xml',
            $extract_dir . '/dir_generic.xml',
        ];
        foreach ($required_files as $required_file) {
            if (!file_exists($required_file) || filesize($required_file) <= 0) {
                throw new Exception('Required extracted file not found: ' . $required_file);
            }
        }
    }

    private function getExtractFilePath(string $file_name): string
    {
        $path = base_path(self::ARCHIVE_EXTRACT_DIR . '/' . $file_name);
        if (!file_exists($path) || filesize($path) <= 0) {
            $this->downloadArchive();
        }
        if (!file_exists($path) || filesize($path) <= 0) {
            throw new Exception('Gama extracted file not found: ' . $path);
        }
        return $path;
    }

    private function updateSessionArchiveMeta(
        int $session_id,
        string $archive_path,
        string $archive_url,
        ?string $archive_hash,
        ?int $archive_size
    ): void
    {
        $this->query('sessions')
            ->where('id', $session_id)
            ->update([
                'source_url' => $archive_url,
                'archive_path' => $archive_path,
                'archive_hash' => $archive_hash,
                'archive_size' => $archive_size,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    private function trackRouteResponse(int $gama_cruise_id, int $session_id, string $status, string $payload): void
    {
        $now = now()->toDateTimeString();
        $this->query('route_responses')->updateOrInsert(
            [
                'gama_cruise_id' => $gama_cruise_id,
                'session_id' => $session_id,
            ],
            [
                'status' => $status,
                'raw_payload' => mb_substr($payload, 0, 200000),
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function buildCategoriesMap(array $category_items): array
    {
        $result = [];
        foreach ($category_items as $item) {
            $data = $item['@attributes'] ?? [];
            $category_id = intval($data['id'] ?? 0);
            if ($category_id <= 0) {
                continue;
            }
            $result[$category_id] = $data;
        }
        return $result;
    }

    private function buildCabinMapForShip(int $ship_id): array
    {
        if ($ship_id <= 0) {
            return [];
        }

        $rows = $this->query('cabins')
            ->where('ship_id', $ship_id)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[intval($row->cabin_id)] = [
                'category_id' => intval($row->cabin_category_id ?? 0),
                'places' => intval($row->places ?? 0),
            ];
        }

        return $result;
    }

    private function buildWaybillByPathRange(array $path_list, int $path_start_id, int $path_finish_id): array
    {
        if ($path_start_id <= 0 || $path_finish_id <= 0) {
            return [];
        }

        $items = [];
        foreach ($path_list as $path_item) {
            $path_data = $path_item['@attributes'] ?? [];
            $path_id = intval($path_data['id'] ?? 0);
            if ($path_id < $path_start_id || $path_id > $path_finish_id) {
                continue;
            }

            $items[] = [
                'path_id' => $path_id,
                'town_name' => (string) ($path_data['town_name'] ?? ''),
                'arrival_at' => (string) ($path_data['s'] ?? ''),
                'departure_at' => (string) ($path_data['f'] ?? ''),
            ];
        }

        return $items;
    }

    private function resolveApiKey(): string
    {
        return trim((string) (env('CHUB_GAMA_API_KEY') ?: self::API_KEY));
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

    public function prodBatches(): array
    {
        $this->ensureGamaDb();

        $cruises = [];
        $this->gama_db->query('cruises')
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
        $this->ensureGamaDb();

        $cruise_id = intval($cruise['cruise_id'] ?? 0);
        $ship_id = intval($cruise['ship_id'] ?? 0);
        if ($cruise_id <= 0 || $ship_id <= 0) {
            return;
        }

        $ship_name = $this->getGamaShipName($ship_id);
        if ($ship_name === null || trim($ship_name) === '') {
            return;
        }

        $checkin_dto = CheckinDto::make(
            provider_code: 'gama',
            provider_checkin_id: $cruise_id,
            date_start: (string) ($cruise['date_start'] ?? ''),
            date_end: (string) ($cruise['date_end'] ?? ''),
        );
        $checkin_dto->resolveShip($ship_name, $ship_id);

        $waybill_rows = $this->gama_db->query('waybills')
            ->where('cruise_id', $cruise_id)
            ->orderBy('order_index')
            ->get();

        foreach ($waybill_rows as $point) {
            $checkin_dto->addWaybillPoint(
                town_name: (string) ($point->town_name ?? ''),
                is_important: false,
                point_type: null,
                arrival_at: !empty($point->arrival_at) ? (string) $point->arrival_at : null,
                departure_at: !empty($point->departure_at) ? (string) $point->departure_at : null,
            );
        }

        $prices = $this->gama_db->query('prices')
            ->where('cruise_id', $cruise_id)
            ->get();

        foreach ($prices as $price_record) {
            $category_id = intval($price_record->cabin_category_id ?? 0);
            if ($category_id <= 0) {
                continue;
            }

            $gama_cabin_category = $this->getGamaCategory($ship_id, $category_id);
            if (!$gama_cabin_category) {
                continue;
            }

            $gama_deck = $this->getGamaDeck($ship_id, $category_id);
            $places_main_qnt = max(0, intval($gama_cabin_category->places ?? 0));
            $places_value = max(1, intval($price_record->persons ?? $places_main_qnt ?: 1));

            $checkin_dto->resolvePrice(
                provider_grade_name: (string) ($gama_cabin_category->name ?? ('Category ' . $category_id)),
                provider_grade_uid: (string) $category_id,
                places_main_qnt: $places_main_qnt,
                places_extra_qnt: 0,
                provider_deck_name: $gama_deck?->name,
                provider_deck_uid: $gama_deck ? (string) $gama_deck->id : null,
                places_value: $places_value,
                price_value: intval($price_record->price_value ?? 0),
                tariff_id: 1,
            );
        }

        $checkin_dto->push();
    }

    private function getGamaDeck(int $ship_id, int $category_id): ?object
    {
        $this->ensureGamaDb();

        $cache_key = $ship_id . ':' . $category_id;
        if (array_key_exists($cache_key, $this->gama_decks_cache)) {
            return $this->gama_decks_cache[$cache_key];
        }

        $deck_ids = $this->gama_db->query('cabins')
            ->where('ship_id', $ship_id)
            ->where('cabin_category_id', $category_id)
            ->pluck('deck_id')
            ->filter(function($deck_id) {
                return intval($deck_id) > 0;
            })
            ->unique()
            ->values()
            ->toArray();

        if (count($deck_ids) !== 1) {
            $this->gama_decks_cache[$cache_key] = null;
            return null;
        }

        $deck = $this->gama_db->query('decks')->find(intval($deck_ids[0]));
        $this->gama_decks_cache[$cache_key] = $deck ?: null;
        return $this->gama_decks_cache[$cache_key];
    }

    private function getGamaShipName(int $ship_id): ?string
    {
        $this->ensureGamaDb();
        $ship = $this->gama_db->query('ships')->find($ship_id);
        if (!$ship) {
            return null;
        }

        $name = trim((string) ($ship->name ?? ''));
        return $name === '' ? null : $name;
    }

    private function getGamaCategory(int $ship_id, int $category_id): ?object
    {
        $this->ensureGamaDb();

        $cache_key = $ship_id . ':' . $category_id;
        if (array_key_exists($cache_key, $this->gama_cabin_categories_cache)) {
            return $this->gama_cabin_categories_cache[$cache_key];
        }

        $category = $this->gama_db->query('cabin_categories')
            ->where('ship_id', $ship_id)
            ->where('category_id', $category_id)
            ->first();

        $this->gama_cabin_categories_cache[$cache_key] = $category ?: null;
        return $this->gama_cabin_categories_cache[$cache_key];
    }

    private function ensureGamaDb(): void
    {
        if ($this->gama_db === null) {
            $this->gama_db = BasesApp::connect(self::BASE_CODE);
        }
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
            $message = 'Failed to parse Gama XML';
            if ($errors) {
                $message .= ': ' . trim($errors[0]->message);
            }
            throw new Exception($message);
        }

        $json = json_encode($xml, JSON_UNESCAPED_UNICODE);
        $result = json_decode((string) $json, true);
        if (!is_array($result)) {
            throw new Exception('Failed to convert Gama XML to array');
        }

        return $result;
    }
}
