<?php namespace Zen\Chub\Classes\Support;

class Vite
{
    public static function urls(array $entries): array
    {
        $normalizedEntries = self::normalizeEntries($entries);
        $plugin_resources_path = plugins_path('zen/chub/resources');

        $assets = [
            'css' => [],
            'js' => []
        ];
        $devHost = 'localhost';
        $devCheckHost = 'axis-vite';
        $devPort = 5173;
        $devOrigin = 'http://'.$devHost.':'.$devPort;
        $devAlive = false;
        try {
            $fp = @fsockopen($devCheckHost, $devPort, $errno, $errstr, 0.15);
            if ($fp) {
                $devAlive = true;
                fclose($fp);
            }
        } catch (\Throwable $e) {}
        if (!$devAlive) {
            try {
                $fp = @fsockopen($devHost, $devPort, $errno, $errstr, 0.15);
                if ($fp) {
                    $devAlive = true;
                    fclose($fp);
                }
            } catch (\Throwable $e) {}
        }

        if ($devAlive && self::canUseDevServer($normalizedEntries, $plugin_resources_path)) {
            $assets['js'][] = $devOrigin.'/@vite/client';
            foreach ($normalizedEntries as $entry) {
                $src = rtrim($devOrigin, '/').'/'.ltrim($entry, '/');
                if (substr($entry, -4) === '.css' || substr($entry, -5) === '.scss') {
                    $assets['css'][] = $src;
                } else {
                    $assets['js'][] = $src;
                }
            }
            return $assets;
        }

        $manifestPath = plugins_path('zen/chub/assets/.vite/manifest.json');
        if (!is_file($manifestPath)) {
            return $assets;
        }

        $json = json_decode(file_get_contents($manifestPath), true) ?: [];
        $assetBase = '/plugins/zen/chub/assets/';

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
