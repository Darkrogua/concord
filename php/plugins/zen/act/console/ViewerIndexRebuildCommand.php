<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Models\Act;

class ViewerIndexRebuildCommand extends Command
{
    protected $signature = 'act:viewer-index-rebuild
        {--act= : UUID одного акта}
        {--dry-run : Только отчёт без записи}';

    protected $description = 'Пересобрать PostgreSQL-индекс видимости актов (zen_act_viewer_index)';

    public function handle(): int
    {
        $filter = trim((string) $this->option('act'));
        $dry_run = (bool) $this->option('dry-run');
        $access = AccessApp::make();

        if ($filter !== '') {
            $act_ids = [$filter];
        } else {
            $act_ids = Act::query()->pluck('id')->map(fn ($id): string => (string) $id)->all();
            $storage = ActStorage::make();
            $root = storage_path('acts');
            if (is_dir($root)) {
                foreach (scandir($root) ?: [] as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    if (is_file($storage->actDirectory($item).'/act.sqlite') && ! in_array($item, $act_ids, true)) {
                        $act_ids[] = $item;
                    }
                }
            }
            sort($act_ids);
        }

        $rebuilt = 0;
        foreach ($act_ids as $act_id) {
            if ($dry_run) {
                $this->line("dry-run: {$act_id}");
                continue;
            }

            try {
                $access->ensureReady($act_id);
                $rows = $access->rebuildViewerIndex($act_id);
                $this->info("{$act_id}: {$rows} строк индекса");
                $rebuilt += $rows;
            } catch (\Throwable $e) {
                $this->warn("{$act_id}: {$e->getMessage()}");
            }
        }

        $this->info($dry_run ? 'Dry-run завершён' : "Готово, строк индекса: {$rebuilt}");

        return self::SUCCESS;
    }
}
