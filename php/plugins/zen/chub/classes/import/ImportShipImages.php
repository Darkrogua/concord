<?php namespace Zen\Chub\Classes\Import;

use Zen\Chub\Classes\Connectors\MysqlConnection;
use Zen\Chub\Models\Ship;
use Zen\Chub\Classes\Import\ComparisonsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportShipImages
{
    private const SOURCE_ATTACHMENT_TYPE = 'Mcmraak\Rivercrs\Models\Motorships';
    private const SOURCE_PUBLIC_BASE_URL = 'https://xn----7sbveuzmbgd.xn--p1ai/storage/app/uploads/public';

    public static function make(): self
    {
        return new self();
    }

    public function handle()
    {
        $ships = Ship::get();
        $ships_total = $ships->count();
        $ships_processed = 0;
        $files_total = 0;
        $files_imported = 0;
        $files_skipped_missing_source_id = 0;
        $files_skipped_empty_disk_name = 0;
        $files_skipped_duplicate = 0;
        $files_skipped_unavailable = 0;

        $this->log("Старт импорта изображений. Кораблей: {$ships_total}");

        foreach ($ships as $ship) {
            $ships_processed++;
            $this->log("Корабль {$ships_processed}/{$ships_total}: #{$ship->id} {$ship->name}");

            $source_id = ComparisonsApp::make()->getSourceId('Ship', $ship->id);
            if (!$source_id) {
                $files_skipped_missing_source_id++;
                $this->log("  Пропуск: нет source_id в ComparisonsApp");
                continue;
            }

            $files = MysqlConnection::make()
                ->azimut74('system_files')
                ->where('attachment_type', self::SOURCE_ATTACHMENT_TYPE)
                ->where('attachment_id', $source_id)
                ->where('field', 'images')
                ->orderBy('sort_order')
                ->get();
            $ship_files_total = $files->count();
            $this->log("  Найдено файлов в источнике: {$ship_files_total} (source_id: {$source_id})");

            foreach ($files as $file_index => $file) {
                $files_total++;
                $disk_name = (string) $file->disk_name;
                $file_number = $file_index + 1;
                $this->log("    Файл {$file_number}/{$ship_files_total} (общий {$files_total}): {$disk_name}");

                if (!$disk_name) {
                    $files_skipped_empty_disk_name++;
                    $this->log("      Пропуск: пустой disk_name");
                    continue;
                }

                // На случай, если файл уже импортирован ранее с private-правами.
                $this->normalizePublicPermissions($disk_name);

                $exists = DB::table('system_files')
                    ->where('attachment_type', Ship::class)
                    ->where('attachment_id', $ship->id)
                    ->where('field', 'images')
                    ->where('disk_name', $disk_name)
                    ->exists();
                if ($exists) {
                    $files_skipped_duplicate++;
                    $this->log("      Пропуск: дубликат уже существует");
                    continue;
                }

                $source_url = $this->buildSourceUrl($disk_name);
                $this->log("      URL: {$source_url}");
                $response = $this->fetchImage($source_url);
                if (!$response) {
                    $files_skipped_unavailable++;
                    $this->log("      Пропуск: URL недоступен или файл пустой");
                    continue;
                }

                $relative_path = $this->buildRelativeStoragePath($disk_name);
                Storage::disk('local')->put($relative_path, $response['body']);
                $this->normalizePublicPermissions($disk_name);

                DB::table('system_files')->insert([
                    'disk_name' => $disk_name,
                    'file_name' => (string) ($file->file_name ?? $disk_name),
                    'file_size' => (int) ($file->file_size ?: strlen($response['body'])),
                    'content_type' => (string) ($file->content_type ?: $response['content_type']),
                    'title' => $file->title,
                    'description' => $file->description,
                    'field' => 'images',
                    'attachment_id' => (string) $ship->id,
                    'attachment_type' => Ship::class,
                    'is_public' => (int) ($file->is_public ?? 1),
                    'sort_order' => (int) ($file->sort_order ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $files_imported++;
                $this->log("      Импортировано: {$relative_path}");
            }
        }

        $this->log('Импорт изображений завершен.');
        $this->log("Итог: кораблей обработано {$ships_processed}/{$ships_total}");
        $this->log("Итог по файлам: всего {$files_total}, импортировано {$files_imported}, дубликаты {$files_skipped_duplicate}, недоступны {$files_skipped_unavailable}, пустой disk_name {$files_skipped_empty_disk_name}, без source_id {$files_skipped_missing_source_id}");
    }

    private function buildSourceUrl(string $disk_name): string
    {
        $nested_path = implode('/', str_split(substr($disk_name, 0, 9), 3));
        return self::SOURCE_PUBLIC_BASE_URL . '/' . $nested_path . '/' . $disk_name;
    }

    private function buildRelativeStoragePath(string $disk_name): string
    {
        $nested_path = implode('/', str_split(substr($disk_name, 0, 9), 3));
        return 'uploads/public/' . $nested_path . '/' . $disk_name;
    }

    private function fetchImage(string $url): ?array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(20)->get($url);
            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '') {
                return null;
            }

            return [
                'body' => $body,
                'content_type' => (string) $response->header('Content-Type', 'application/octet-stream'),
            ];
        }
        catch (Throwable $exception) {
            return null;
        }
    }

    private function normalizePublicPermissions(string $disk_name): void
    {
        $segments = str_split(substr($disk_name, 0, 9), 3);
        $absolute_base = storage_path('app/uploads/public');
        $absolute_path = $absolute_base;

        foreach ($segments as $segment) {
            $absolute_path .= '/' . $segment;

            if (is_dir($absolute_path)) {
                @chmod($absolute_path, 0775);
            }
        }

        $absolute_file = storage_path('app/' . $this->buildRelativeStoragePath($disk_name));
        if (is_file($absolute_file)) {
            @chmod($absolute_file, 0644);
        }
    }

    private function log(string $message): void
    {
        echo '[ImportShipImages] ' . $message . PHP_EOL;
    }
}