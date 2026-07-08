<?php namespace Zen\Chub\Classes\System;

use Illuminate\Support\Collection;
use File;

class Files
{
    public static function make()
    {
        return new self();
    }

    /**
     * Проверить адрес файла и рекурсивно создать недостающие папки
     * @param string $path
     * @param int $permissions
     * @return string
     */
    public function defineFilePath(string $path, int $permissions = 0777): string
    {
        # Проверяем, считается ли путь директорией (по завершающему слэшу)
        $is_directory = str_ends_with($path, DIRECTORY_SEPARATOR);

        # Определяем, какую директорию нужно создать
        $target_dir = $is_directory ? rtrim($path, DIRECTORY_SEPARATOR) : dirname($path);

        # Создаём директорию, если её нет
        if (!is_dir($target_dir)) {
            mkdir($target_dir, $permissions, true);
        }

        # Возвращаем путь без завершающего слэша
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Возвращает коллекцию со списком файлов в указанной папке
     * @param string $dir_path - Путь к папке
     * @param bool $recursive - Рекурсивное сканирование (отключено по умолчанию)
     * @param array $settings - Настройки исключений exclude_names, exclude_paths, allowed_extensions
     * @return Collection
     */
    public function filesList(string $dir_path, bool $recursive = false, array $settings = []): Collection
    {
        $files = $recursive ? File::allFiles($dir_path) : File::files($dir_path);
        $output = [];
        $excluded_patterns = $settings['excluded'] ?? [];
        $allowed_extensions = $settings['allowed_extensions'] ?? [];

        foreach ($files as $file) {
            try {
                if ($file->isLink() || !file_exists($file->getRealPath())) {
                    $size = 0;
                } else {
                    $size = $file->getSize();
                }
            } catch (\Exception $e) {
                $size = 0;
            }

            $path = $file->getRealPath();
            if (empty($path)) continue;

            // Проверка по маскам
            if ($this->matchesPatterns($path, $excluded_patterns)) {
                continue;
            }

            // Проверка расширений, если указаны
            if (!empty($allowed_extensions) && !in_array($file->getExtension(), $allowed_extensions)) {
                continue;
            }

            $output[] = [
                'name' => $file->getFilename(),
                'extension' => $file->getExtension(),
                'path' => $path,
                'size' => intval($size)
            ];
        }

        return collect($output);
    }

    /**
     * Вспомогательный метод для this.filesList
     * Проверяет путь на совпадение с любым паттерном из списка $patterns
     * @param string $path
     * @param array $patterns
     * @return bool
     */
    private function matchesPatterns(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Убираем * для упрощённой логики "начинается с"
            $prefix = rtrim($pattern, '*');

            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Рекурсивное удаление содержимого директории
     * @param string $dir_path
     * @return void
     */
    public function shellRemoveDir(string $dir_path): void
    {
        $real_path = realpath($dir_path);

        if ($real_path === false || !is_dir($real_path) || $this->isDangerousPath($real_path)) {
            return;
        }

        $escaped_path = escapeshellarg($real_path);
        shell_exec("rm -rf $escaped_path/*");
    }

    private function isDangerousPath(string $path): bool
    {
        $normalized_path = rtrim($path, DIRECTORY_SEPARATOR);

        if ($normalized_path === '' || $normalized_path === DIRECTORY_SEPARATOR) {
            return true;
        }

        $blocked_paths = [
            '/bin',
            '/boot',
            '/dev',
            '/etc',
            '/home',
            '/lib',
            '/lib64',
            '/proc',
            '/root',
            '/run',
            '/sbin',
            '/srv',
            '/sys',
            '/usr',
            '/var',
        ];

        if (in_array($normalized_path, $blocked_paths, true)) {
            return true;
        }

        if (function_exists('base_path')) {
            $project_root = rtrim((string) base_path(), DIRECTORY_SEPARATOR);

            if ($project_root !== '' && ($normalized_path === $project_root || !str_starts_with($normalized_path, $project_root . DIRECTORY_SEPARATOR))) {
                return true;
            }
        }

        return false;
    }
}
