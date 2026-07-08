<?php namespace Zen\Chub\Classes\Parsers\Waterway;

use Exception;

/**
 * Файловый кеш ответов API ВодоходЪ (совместим с legacy worker: тот же каталог).
 */
class WaterwayCache
{
    private string $cache_dir;

    public function __construct()
    {
        $this->cache_dir = storage_path('parsers_cache/waterway');
        if (!is_dir($this->cache_dir)) {
            if (!mkdir($this->cache_dir, 0775, true)) {
                throw new Exception("Не удалось создать директорию кеша: {$this->cache_dir}");
            }
        }
    }

    public function get(string $key): mixed
    {
        $file_path = $this->getCachePath($key);
        if (!file_exists($file_path)) {
            return null;
        }
        try {
            $content = file_get_contents($file_path);
            if ($content === false) {
                return null;
            }
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                @unlink($file_path);
                return null;
            }
            return $data;
        } catch (Exception $e) {
            return null;
        }
    }

    public function put(string $key, mixed $data): bool
    {
        $file_path = $this->getCachePath($key);
        $dir = dirname($file_path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new Exception("Не удалось создать директорию: {$dir}");
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new Exception('Ошибка кодирования JSON: ' . json_last_error_msg());
        }
        if (file_put_contents($file_path, $json, LOCK_EX) === false) {
            throw new Exception("Не удалось записать файл кеша: {$file_path}");
        }
        chmod($file_path, 0664);
        return true;
    }

    public function has(string $key): bool
    {
        $file_path = $this->getCachePath($key);
        return file_exists($file_path) && is_readable($file_path);
    }

    public function clear(): bool
    {
        if (!is_dir($this->cache_dir)) {
            return true;
        }
        $files = glob($this->cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            } elseif (is_dir($file)) {
                $this->removeDirectory($file);
            }
        }
        return true;
    }

    private function getCachePath(string $key): string
    {
        return $this->cache_dir . '/' . md5($key) . '.json';
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
