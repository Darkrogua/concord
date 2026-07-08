<?php namespace Zen\Chub\Classes\Parsers\Waterway;

use Exception;

class WaterwayApiClient
{
    private const string DEFAULT_API_URL = 'https://api-crs.vodohod.com';
    private const string DEFAULT_API_TOKEN = 'JYMucmvXoUwDruvgo';
    private const string DEFAULT_API_LOGIN = 'azimut-trk+vodohodapi@yandex.ru';

    private int $timeout;
    private string $api_url;
    private string $api_token;
    private string $api_login;
    private ?string $access_token = null;
    private WaterwayCache $cache;
    private int $max_query_attempts = 3;
    private $command = null;

    public function __construct(int $timeout = 30)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('max_input_time', '-1');
        $this->timeout = $timeout;
        $this->api_url = rtrim((string) (env('CHUB_WATERWAY_API_URL') ?: self::DEFAULT_API_URL), '/');
        $this->api_token = (string) (env('CHUB_WATERWAY_API_TOKEN') ?: self::DEFAULT_API_TOKEN);
        $this->api_login = (string) (env('CHUB_WATERWAY_API_LOGIN') ?: self::DEFAULT_API_LOGIN);
        $this->cache = new WaterwayCache();
    }

    public function setCommand($command): self
    {
        $this->command = $command;
        return $this;
    }

    private function consoleLine(string $message): void
    {
        if ($this->command && method_exists($this->command, 'line')) {
            $this->command->line('[' . date('H:i:s') . '] ' . $message);
        }
    }

    private function auth(): void
    {
        $cache_key = 'waterway_auth_token';
        if ($this->cache->has($cache_key)) {
            $cached_token = $this->cache->get($cache_key);
            if ($cached_token !== null && $cached_token !== '') {
                $this->access_token = $cached_token;
                return;
            }
        }

        $data = [
            'login' => $this->api_login,
            'password' => $this->api_token,
        ];

        $response = $this->httpQuery([
            'method' => 'security.authorise',
            'data' => $data,
        ]);

        if ($response->code !== 200 || !isset($response->body['result']['accessToken']['token'])) {
            $error_msg = 'Ошибка авторизации в API Waterway';
            if (isset($response->body['message'])) {
                $error_msg .= ': ' . $response->body['message'];
            }
            if ($response->code !== 200) {
                $error_msg .= " (HTTP {$response->code})";
            }
            throw new Exception($error_msg);
        }

        $this->access_token = $response->body['result']['accessToken']['token'];
        $this->cache->put($cache_key, $this->access_token);
    }

    private function httpQuery(array $opts): object
    {
        $default = [
            'method' => null,
            'data' => null,
            'timeout' => null,
        ];
        $opts = (object) array_merge($default, $opts);
        $method = str_replace('.', '/', (string) $opts->method);
        $url = "{$this->api_url}/{$method}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if ($opts->timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $opts->timeout);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        }

        $headers = [];
        if ($opts->data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            $post = json_encode($opts->data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . mb_strlen($post);
        }
        if ($this->access_token) {
            $headers[] = "Authorization: Bearer {$this->access_token}";
        }
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        if ($code !== 200) {
            file_put_contents(storage_path('worker_last_response.txt'), (string) $response);
        }
        curl_close($ch);
        if ($response) {
            $response = json_decode($response, true);
        }
        return (object) [
            'code' => $code,
            'body' => $response,
        ];
    }

    private function wwQuery(string $method, ?array $data = null, ?string $cache_key = null): mixed
    {
        if (!$cache_key) {
            $cache_key = 'waterway_' . md5($method . json_encode($data));
        }
        $query_attempts = $this->max_query_attempts;
        $was_cached = $this->cache->has($cache_key);
        if ($was_cached) {
            $cached = $this->cache->get($cache_key);
            if ($cached === null) {
                return null;
            }
            return $cached;
        }
        if (!$this->access_token) {
            $this->auth();
        }
        return $this->wwQueryWithRetries($method, $data, $cache_key, $query_attempts, $was_cached);
    }

    private function wwQueryWithRetries(string $method, ?array $data, string $cache_key, int $query_attempts, bool $was_cached): mixed
    {
        $opts = ['method' => $method];
        if ($data) {
            $opts['data'] = $data;
        }
        $response = $this->httpQuery($opts);

        if (isset($response->body['error'])) {
            $error_code = $response->body['error']['error_code'] ?? 'unknown';
            $error_msg = $response->body['error']['error_msg'] ?? 'Unknown error';
            if ($error_code == 429) {
                sleep(10);
                return $this->wwQuery($method, $data, $cache_key);
            }
            if ($error_code == 403 && strpos($method, 'security.authorise') === false) {
                $this->cache->put($cache_key, null);
                return null;
            }
            $this->cache->put($cache_key, null);
            throw new Exception("API error: {$error_code} - {$error_msg}");
        }

        if ($response->code == 403 || $response->code != 200 || intval(@$response->body['code']) != 200) {
            if ($response->code === 429) {
                sleep(10);
                return $this->wwQuery($method, $data, $cache_key);
            }
            if ($response->code == 403 && strpos($method, 'security.authorise') === false) {
                $this->cache->put($cache_key, null);
                return null;
            }
            if ($response->code == 403 && strpos($method, 'security.authorise') !== false) {
                $this->access_token = null;
                $this->cache->put('waterway_auth_token', null);
                $this->auth();
            }
            $query_attempts--;
            if ($query_attempts < 0) {
                throw new Exception('error ww1 ' . $method);
            }
            if ($response->code === 500) {
                throw new Exception('error ww1 ' . $method);
            }
            return $this->wwQueryWithRetries($method, $data, $cache_key, $query_attempts, $was_cached);
        }

        $this->cache->put($cache_key, $response->body);
        if (!$was_cached) {
            usleep(200000);
        }
        return $response->body;
    }

    public function getMotorships(): array
    {
        $cache_key = 'waterway_motorships';
        $response = $this->wwQuery('json.v3.motorships?limit=100', null, $cache_key);
        if (!isset($response['result']['data'])) {
            throw new Exception('API вернул некорректные данные о теплоходах');
        }
        $ships = $response['result']['data'];
        $result = [];
        foreach ($ships as $ship) {
            $result[$ship['id']] = [
                'name' => $ship['name'],
                'type' => $ship['type'] ?? null,
                'description' => $ship['description'] ?? '',
            ];
        }
        return $result;
    }

    public function getCruises(): array
    {
        $cache_key = 'waterway_cruises';
        if ($this->cache->has($cache_key)) {
            $cached_cruises = $this->cache->get($cache_key);
            if (is_array($cached_cruises)) {
                foreach ($cached_cruises as $cruise_id => &$cruise) {
                    if (isset($cruise['days']) && $cruise['days'] > 100) {
                        $cruise['days'] = (int) ($cruise['days'] / 86400);
                    }
                }
                unset($cruise);
            }
            $this->consoleLine('Список круизов: кеш hit (' . (is_array($cached_cruises) ? count($cached_cruises) : 0) . ')');
            return is_array($cached_cruises) ? $cached_cruises : [];
        }
        $this->consoleLine('Список круизов: кеш miss, получаем из API батчами...');
        $now_day = time();
        $batch = 100;
        $offset = 0;
        $all_cruises = [];
        $max_attempts = 3;
        $error_count = 0;

        while (true) {
            $query_params = http_build_query([
                'limit' => $batch,
                'offset' => $offset,
                'filter' => [
                    'durationFrom' => [2],
                    'dateFrom' => $now_day,
                ],
            ]);
            $method = "json.v3.cruises?{$query_params}";
            $batch_cache_key = "waterway_cruises_batch_{$offset}";
            try {
                $response = $this->wwQuery($method, null, $batch_cache_key);
                if ($response === null) {
                    $offset += $batch;
                    $error_count++;
                    if ($error_count >= $max_attempts) {
                        break;
                    }
                    continue;
                }
                if (!isset($response['result']['data'])) {
                    break;
                }
                $cruises = $response['result']['data'];
                if (empty($cruises)) {
                    break;
                }
                foreach ($cruises as $cruise) {
                    $duration_seconds = $cruise['duration'] ?? 0;
                    $days = $duration_seconds > 0 ? (int) ($duration_seconds / 86400) : 0;
                    $all_cruises[$cruise['id']] = [
                        'name' => $cruise['name'] ?? '',
                        'motorshipId' => $cruise['motorship']['id'] ?? null,
                        'dateStart' => $cruise['dateStart'] ?? null,
                        'dateStop' => $cruise['dateEnd'] ?? null,
                        'days' => $days,
                        'classDescription' => $cruise['classDescription'] ?? null,
                    ];
                }
                $count = intval($response['result']['count'] ?? 0);
                $offset += $batch;
                $error_count = 0;
                $this->consoleLine('Получено круизов: ' . count($all_cruises) . " (offset={$offset}, всего: {$count})");
                if ($offset >= $count || count($cruises) < $batch) {
                    break;
                }
            } catch (Exception $e) {
                $error_count++;
                if ($error_count >= $max_attempts) {
                    break;
                }
                $offset += $batch;
            }
        }

        if (empty($all_cruises)) {
            return [];
        }
        $this->cache->put($cache_key, $all_cruises);
        return $all_cruises;
    }

    public function getCruisePrices(int $cruise_id): ?array
    {
        $cache_key = "waterway_prices_{$cruise_id}";
        $response = $this->wwQuery("json.v3.cruise.room-tariffs?id={$cruise_id}", null, $cache_key);
        if (!isset($response['result'])) {
            $this->cache->put($cache_key, null);
            return null;
        }
        $result = ['tariffs' => []];
        if (isset($response['result']['decks'])) {
            foreach ($response['result']['decks'] as $deck) {
                if (!isset($deck['roomClasses']) || !is_array($deck['roomClasses'])) {
                    continue;
                }
                foreach ($deck['roomClasses'] as $room_class) {
                    if (!isset($room_class['tariffs']) || !is_array($room_class['tariffs']) || empty($room_class['tariffs'])) {
                        continue;
                    }
                    foreach ($room_class['tariffs'] as $tariff) {
                        if (!isset($tariff['accommodations']) || !is_array($tariff['accommodations']) || empty($tariff['accommodations'])) {
                            continue;
                        }
                        foreach ($tariff['accommodations'] as $accommodation) {
                            $tariff_name = $accommodation['name'] ?? '';
                            if ($tariff_name === '') {
                                continue;
                            }
                            $is_base = ($tariff_name === 'Тариф Взрослый' || $tariff_name === 'Тариф взрослый');
                            $is_extended = ($tariff_name === 'Тариф Взрослый расширенный');
                            if (!$is_base && !$is_extended) {
                                continue;
                            }
                            if (!isset($accommodation['price'])) {
                                continue;
                            }
                            $price_value = intval(($accommodation['price']['discountedValue'] ?? $accommodation['price']['value'] ?? 0) / 100);
                            $places_qnt = intval($accommodation['id'] ?? 1);
                            if ($places_qnt <= 0) {
                                $places_qnt = 1;
                            }
                            if ($price_value > 0) {
                                if (!isset($result['tariffs'][$tariff_name])) {
                                    $result['tariffs'][$tariff_name] = [
                                        'tariff_name' => $tariff_name,
                                        'prices' => [],
                                    ];
                                }
                                $result['tariffs'][$tariff_name]['prices'][] = [
                                    'rt_name' => $room_class['name'] ?? '',
                                    'rt_id' => $room_class['id'] ?? null,
                                    'rt_meta_name' => $room_class['meta_name'] ?? null,
                                    'rp_name' => $room_class['description'] ?? null,
                                    'rp_id' => $room_class['meta_id'] ?? null,
                                    'deck_id' => $deck['id'] ?? null,
                                    'deck_name' => $deck['name'] ?? null,
                                    'deck_meta_id' => $deck['meta_id'] ?? null,
                                    'deck_meta_name' => $deck['meta_name'] ?? null,
                                    'price_value' => $price_value,
                                    'places_qnt' => $places_qnt,
                                ];
                            }
                        }
                    }
                }
            }
        }
        if (empty($result['tariffs'])) {
            $this->cache->put($cache_key, null);
            return null;
        }
        return $result;
    }

    public function getCruiseRoute(int $cruise_id): ?array
    {
        $cache_key = "waterway_route_{$cruise_id}";
        $cruise_response = $this->wwQuery("json.v3.cruise?id={$cruise_id}", null, "waterway_cruise_{$cruise_id}");
        if (!is_array($cruise_response) || !isset($cruise_response['result']['route'])) {
            $this->cache->put($cache_key, null);
            return null;
        }
        $routes = $cruise_response['result']['route'];
        $result = [];
        $day = 1;
        foreach ($routes as $route) {
            $result[] = [
                'day' => $day++,
                'portName' => $route['name'] ?? '',
                'excursion' => $route['annotation'] ?? '',
                'timeStart' => isset($route['in']) ? date('H:i:s', strtotime($route['in'])) : '00:00:00',
                'timeStop' => isset($route['out']) ? date('H:i:s', strtotime($route['out'])) : '00:00:00',
            ];
        }
        if (empty($result)) {
            $this->cache->put($cache_key, null);
            return null;
        }
        $this->cache->put($cache_key, $result);
        return $result;
    }
}
