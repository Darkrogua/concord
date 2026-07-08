<?php namespace Zen\Act\Classes\Support;

/**
 * Подключение Vite (dev-сервер или build + manifest), по тому же принципу, что Zen\Chub\Classes\Support\Vite.
 *
 * Переменные .env:
 * - ACT_VITE_ORIGIN — URL, с которого браузер грузит модули (например http://localhost:5174 при docker 5174:5173)
 * - ACT_VITE_DEV_HOST — хост для проверки из PHP-контейнера (например os3-vite)
 * - ACT_VITE_DEV_PORT — порт Vite внутри сети Docker (обычно 5173)
 */
class Vite
{
    public static function urls(array $entries): array
    {
        $normalizedEntries = self::normalizeEntries($entries);
        $pluginResourcesPath = plugins_path('zen/act/resources');

        $assets = [
            'css' => [],
            'js' => []
        ];

        $devHost = parse_url((string) env('ACT_VITE_ORIGIN', 'http://localhost:5174'), PHP_URL_HOST) ?: 'localhost';
        $devPublicPort = (int) (parse_url((string) env('ACT_VITE_ORIGIN', 'http://localhost:5174'), PHP_URL_PORT) ?: 5174);
        $devOrigin = rtrim((string) env('ACT_VITE_ORIGIN', 'http://localhost:5174'), '/');

        $devCheckHost = (string) env('ACT_VITE_DEV_HOST', 'os3-vite');
        $devCheckPort = (int) env('ACT_VITE_DEV_PORT', 5173);

        $devAlive = false;
        try {
            $fp = @fsockopen($devCheckHost, $devCheckPort, $errno, $errstr, 0.15);
            if ($fp) {
                $devAlive = true;
                fclose($fp);
            }
        } catch (\Throwable $e) {
        }
        if (!$devAlive) {
            try {
                $fp = @fsockopen($devHost, $devPublicPort, $errno, $errstr, 0.15);
                if ($fp) {
                    $devAlive = true;
                    fclose($fp);
                }
            } catch (\Throwable $e) {
            }
        }

        if ($devAlive && self::canUseDevServer($normalizedEntries, $pluginResourcesPath)) {
            $assets['js'][] = $devOrigin.'/@vite/client';
            foreach ($normalizedEntries as $entry) {
                $src = $devOrigin.'/'.ltrim($entry, '/');
                if (substr($entry, -4) === '.css' || substr($entry, -5) === '.scss') {
                    $assets['css'][] = $src;
                } else {
                    $assets['js'][] = $src;
                }
            }

            return $assets;
        }

        $manifestPath = plugins_path('zen/act/assets/.vite/manifest.json');
        if (!is_file($manifestPath)) {
            return $assets;
        }

        $json = json_decode(file_get_contents($manifestPath), true) ?: [];
        $assetBase = '/plugins/zen/act/assets/';

        foreach ($normalizedEntries as $entry) {
            $item = $json[$entry] ?? null;
            if (!$item) {
                $item = $json['resources/'.$entry] ?? null;
            }
            if (!$item) {
                $trimmedEntry = trim($entry, './');
                $entryWithoutPrefix = preg_replace('/^[^\\/]+\\//', '', $trimmedEntry);
                $candidates = [
                    $trimmedEntry,
                    trim('resources/'.$trimmedEntry, './')
                ];
                if (!empty($entryWithoutPrefix) && $entryWithoutPrefix !== $trimmedEntry) {
                    $candidates[] = trim($entryWithoutPrefix, './');
                    $candidates[] = trim('resources/'.$entryWithoutPrefix, './');
                }

                foreach ($json as $k => $v) {
                    if (empty($v['src'])) {
                        continue;
                    }

                    $src = trim((string) $v['src'], './');
                    foreach ($candidates as $candidate) {
                        $candidate = trim((string) $candidate, './');
                        if ($candidate === '') {
                            continue;
                        }
                        if ($src === $candidate || str_ends_with($src, '/'.$candidate)) {
                            $item = $v;
                            break 2;
                        }
                    }
                }
            }
            if (!$item) {
                continue;
            }
            if (!empty($item['css']) && is_array($item['css'])) {
                foreach ($item['css'] as $cssFile) {
                    $assets['css'][] = $assetBase.$cssFile;
                }
            }
            if (!empty($item['file'])) {
                $src = $assetBase.$item['file'];
                if (substr($item['file'], -4) === '.css') {
                    $assets['css'][] = $src;
                } else {
                    $assets['js'][] = $src;
                }
            }
        }

        return $assets;
    }

    public static function tags(array $entries): string
    {
        $assets = self::urls($entries);
        $tags = [];

        foreach ($assets['css'] as $href) {
            $tags[] = '<link rel="stylesheet" href="'.$href.'" />';
        }
        foreach ($assets['js'] as $src) {
            $tags[] = '<script type="module" src="'.$src.'"></script>';
        }

        return implode("\n", $tags);
    }

    private static function normalizeEntries(array $entries): array
    {
        $normalize = function ($value) {
            $trimmed = ltrim((string) $value, './');
            if (strpos($trimmed, 'resources/') === 0) {
                $trimmed = substr($trimmed, strlen('resources/'));
            }

            return $trimmed;
        };

        return array_map($normalize, $entries);
    }

    private static function canUseDevServer(array $entries, string $plugin_resources_path): bool
    {
        foreach ($entries as $entry) {
            $entry_path = $plugin_resources_path.'/'.ltrim($entry, '/');
            if (!is_file($entry_path)) {
                return false;
            }
        }

        return true;
    }
}
