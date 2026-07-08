<?php namespace Zen\Act\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Classes\System\ActApp;
use Zen\Act\Classes\System\SnapshotApp;
use Zen\Act\Models\Act;

class SnapshotsMigrateCommand extends Command
{
    protected $signature = 'act:snapshots-migrate
        {--act= : UUID одного акта}
        {--delete-legacy : Удалить каталог states/ после успешного импорта}';

    protected $description = 'Мигрировать legacy states/*.json в SQLite snapshots и current.json v2';

    public function handle(): int
    {
        $filter = trim((string) $this->option('act'));
        $delete_legacy = (bool) $this->option('delete-legacy');
        $storage = ActStorage::make();

        if ($filter !== '') {
            $act_ids = [$filter];
        } else {
            $root = storage_path('acts');
            $act_ids = [];
            if (is_dir($root)) {
                foreach (scandir($root) ?: [] as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    if (is_dir($root.'/'.$item)) {
                        $act_ids[] = $item;
                    }
                }
            }
            sort($act_ids);
        }

        $imported = 0;
        $failed = 0;

        foreach ($act_ids as $act_id) {
            try {
                $count = $this->migrateAct($act_id, $delete_legacy);
                $this->line(sprintf('  %s — imported %d snapshot(s)', $act_id, $count));
                $imported++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error(sprintf('  %s — %s', $act_id, $exception->getMessage()));
            }
        }

        $this->info(sprintf('Готово: acts=%d, failed=%d', $imported, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateAct(string $act_id, bool $delete_legacy): int
    {
        $storage = ActStorage::make();
        $dir = $storage->actDirectory($act_id);
        if (! is_dir($dir)) {
            throw new \RuntimeException('Каталог акта не найден');
        }

        $snapshot_app = SnapshotApp::make();
        $existing = $snapshot_app->count($act_id);
        $imported = 0;

        $states_root = $dir.'/states';
        if (is_dir($states_root) && $existing === 0) {
            $files = [];
            foreach (scandir($states_root) ?: [] as $state_uuid) {
                if ($state_uuid === '.' || $state_uuid === '..') {
                    continue;
                }
                $state_dir = $states_root.'/'.$state_uuid;
                if (! is_dir($state_dir)) {
                    continue;
                }
                foreach (scandir($state_dir) ?: [] as $file) {
                    if (! str_ends_with($file, '.json')) {
                        continue;
                    }
                    $files[] = [
                        'path' => $state_dir.'/'.$file,
                        'timestamp' => pathinfo($file, PATHINFO_FILENAME),
                    ];
                }
            }

            usort($files, fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp']));

            foreach ($files as $file) {
                $legacy = $storage->readSnapshotFile($file['path']);
                if ($legacy === null) {
                    continue;
                }
                $created_at = $this->timestampToIso((string) $file['timestamp']);
                $snapshot_app->importLegacyJson($act_id, $legacy, $created_at, 'checkpoint');
                $imported++;
            }
        }

        $current = $storage->readCurrentFile($act_id);
        if ($current !== null) {
            ActApp::make()->syncCurrentFile($act_id);
        } elseif (Act::find($act_id)) {
            ActApp::make()->syncCurrentFile($act_id);
        }

        if ($delete_legacy) {
            $storage->removeLegacyStatesDirectory($act_id);
        }

        return $imported;
    }

    private function timestampToIso(string $timestamp): string
    {
        if (preg_match('/^\d{14}$/', $timestamp) !== 1) {
            return Carbon::now('UTC')->toIso8601String();
        }

        return Carbon::createFromFormat('YmdHis', $timestamp, 'UTC')->toIso8601String();
    }
}
