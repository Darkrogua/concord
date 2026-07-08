<?php namespace Zen\Chub\Classes\Connectors;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Zen\Chub\Classes\System\LogsApp;

class Uon
{
    private const int DEFAULT_RATE_LIMIT_RPS = 10;

    private string $api_key;
    private int $cache_time_min = 0;

    public function __construct()
    {
        $this->api_key = env('UON_API_KEY');
        if (env('APP_ENV') === 'local') {
            $this->cache_time_min = 180;
        }
    }

    public static function make()
    {
        return new self();
    }

    public function queryRequest(
        int $request_id,
        int $max_retries = 3,
        int $retry_delay_ms = 500,
        int $timeout_seconds = 10
    ): array {
        $url = "https://api.u-on.ru/{$this->api_key}/request/$request_id.json";

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->httpRequest(
            $url,
            $max_retries,
            $retry_delay_ms,
            $timeout_seconds
        );

        if (!$response->successful()) {
            $status_code = $response->status();
            $this->logApiFailure($url, $status_code);
            throw new \RuntimeException(
                "UON API request failed after {$max_retries} retries: HTTP {$status_code}"
            );
        }

        $response = $response->json();
        /*
        LogsApp::addInfo([
            '$url' => $url,
            '$response' => $response,
        ], 'Запрос к U-ON - Детали заявки');
        */
        return $response;
    }

    public function queryRequestsList(
        string $date_from,
        string $date_to,
        int $page = 1,
        int $max_retries = 3,
        int $retry_delay_ms = 500,
        int $timeout_seconds = 10
    ) {
        $url = "https://api.u-on.ru/{$this->api_key}/requests/$date_from/$date_to/$page.json";

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->httpRequest(
            $url,
            $max_retries,
            $retry_delay_ms,
            $timeout_seconds
        );

        if (!$response->successful()) {
            $status_code = $response->status();
            $this->logApiFailure($url, $status_code);
            throw new \RuntimeException(
                "UON API request failed after {$max_retries} retries: HTTP {$status_code}"
            );
        }

        $response = $response->json();
        /*
        LogsApp::addInfo([
            '$url' => $url,
            '$response' => $response,
        ], 'Запрос к U-ON - Список заявок');
        */
        return $response;
    }

    public function queryPayments(
        string $date_from,
        string $date_to,
        int $page = 1,
        int $max_retries = 3,
        int $retry_delay_ms = 500,
        int $timeout_seconds = 10
    ) {
        // https://api.u-on.ru/XMAMq0nr601eo6tFQL0q1769673699/payment/list/2026-02-27/2026-02-28/1.json
        $url = "https://api.u-on.ru/{$this->api_key}/payment/list/$date_from/$date_to/$page.json";

        /** @var \Illuminate\Http\Client\Response $response */
        $response = $this->httpRequest(
            $url,
            $max_retries,
            $retry_delay_ms,
            $timeout_seconds
        );

        if (!$response->successful()) {
            $status_code = $response->status();
            $this->logApiFailure($url, $status_code);
            throw new \RuntimeException(
                "UON API request failed after {$max_retries} retries: HTTP {$status_code}"
            );
        }

        $response = $response->json();
        /*
        LogsApp::addInfo([
            '$url' => $url,
            '$response' => $response,
        ], 'Запрос к U-ON - Список платежей');
        */
        return $response;
    }

    private function logApiFailure(string $url, int $status_code): void
    {
        LogsApp::addError([
            'url' => preg_replace('#https://api\.u-on\.ru/[^/]+/#', 'https://api.u-on.ru/{key}/', $url),
            'status' => $status_code,
        ], 'UON API: запрос не удался');
    }

    private function httpRequest(
        $url,
        int $max_retries = 3,
        int $retry_delay_ms = 500,
        int $timeout_seconds = 10
    )
    {
        $cache_key = 'uon:http:' . md5($url);

        if ($this->cache_time_min === 0) {
            $this->applyRateLimit();
            return Http::timeout($timeout_seconds)
                ->retry($max_retries, $retry_delay_ms)
                ->get($url);
        }

        $cached = Cache::remember(
            $cache_key,
            now()->addMinutes($this->cache_time_min),
            function () use ($url, $max_retries, $retry_delay_ms, $timeout_seconds) {
                $this->applyRateLimit();
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout($timeout_seconds)
                    ->retry($max_retries, $retry_delay_ms)
                    ->get($url);
                return [
                    'body' => $response->body(),
                    'status' => $response->status(),
                ];
            }
        );

        $psr = new \GuzzleHttp\Psr7\Response($cached['status'], [], $cached['body']);
        return new \Illuminate\Http\Client\Response($psr);
    }

    private function applyRateLimit(): void
    {
        $requests_per_second = intval(env('UON_RATE_LIMIT_RPS', self::DEFAULT_RATE_LIMIT_RPS));
        if ($requests_per_second <= 0) {
            $requests_per_second = self::DEFAULT_RATE_LIMIT_RPS;
        }

        $min_interval_us = (int) floor(1000000 / $requests_per_second);
        if ($min_interval_us < 1) {
            $min_interval_us = 1;
        }

        $locks_dir = storage_path('chub/locks');
        if (!is_dir($locks_dir)) {
            @mkdir($locks_dir, 0775, true);
        }

        $rate_limit_file_path = $locks_dir . '/uon-rate-limit.state';
        $rate_limit_file = @fopen($rate_limit_file_path, 'c+');
        if (!$rate_limit_file) {
            return;
        }

        try {
            if (!flock($rate_limit_file, LOCK_EX)) {
                return;
            }

            rewind($rate_limit_file);
            $last_request_ts = trim((string) stream_get_contents($rate_limit_file));
            $last_request_ts = $last_request_ts !== '' ? floatval($last_request_ts) : 0.0;

            $now_ts = microtime(true);
            $elapsed_us = (int) (($now_ts - $last_request_ts) * 1000000);

            if ($last_request_ts > 0 && $elapsed_us < $min_interval_us) {
                $sleep_us = $min_interval_us - $elapsed_us;
                usleep($sleep_us);
                $now_ts = microtime(true);
            }

            rewind($rate_limit_file);
            ftruncate($rate_limit_file, 0);
            fwrite($rate_limit_file, (string) $now_ts);
            fflush($rate_limit_file);
        } finally {
            flock($rate_limit_file, LOCK_UN);
            fclose($rate_limit_file);
        }
    }
}