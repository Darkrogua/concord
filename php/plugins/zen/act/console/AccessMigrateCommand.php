<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Classes\System\AccessApp;

class AccessMigrateCommand extends Command
{
    protected $signature = 'act:access-migrate
        {--act= : UUID одного акта}
        {--dry-run : Только отчёт без записи}';

    protected $description = 'Миграция legacy block.data.visibility в SQLite access';

    public function handle(): int
    {
        $filter = trim((string) $this->option('act'));
        $dry_run = (bool) $this->option('dry-run');
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
                    $sqlite = $root.'/'.$item.'/act.sqlite';
                    if (is_file($sqlite)) {
                        $act_ids[] = $item;
                    }
                }
            }
            sort($act_ids);
        }

        $migrated = 0;
        foreach ($act_ids as $act_id) {
            $sqlite = $storage->actDirectory($act_id).'/act.sqlite';
            if (! is_file($sqlite)) {
                $this->warn("Пропуск {$act_id}: act.sqlite не найден");
                continue;
            }

            if ($dry_run) {
                $this->line("dry-run: {$act_id}");
                continue;
            }

            $count = AccessApp::make()->migrateLegacyVisibility($act_id);
            $this->info("{$act_id}: обработано {$count}");
            $migrated += $count;
        }

        $this->info($dry_run ? 'Dry-run завершён' : "Миграция завершена, изменений: {$migrated}");

        return self::SUCCESS;
    }
}
