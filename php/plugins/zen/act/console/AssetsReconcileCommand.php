<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\Support\ActAssets;
use Zen\Act\Classes\Support\ActStorage;

class AssetsReconcileCommand extends Command
{
    protected $signature = 'act:assets-reconcile
        {--act= : UUID акта (если не указан — все каталоги storage/acts)}';

    protected $description = 'Удалить файлы изображений галереи, на которые нет ссылок в текущем состоянии блоков';

    public function handle(): int
    {
        $act_id = trim((string) $this->option('act'));
        $act_ids = $act_id !== ''
            ? [$act_id]
            : ActStorage::make()->listActDirectoriesWithCurrent();

        if ($act_ids === []) {
            $this->warn('Нет актов для обработки');

            return self::SUCCESS;
        }

        $total_files = 0;
        $total_dirs = 0;

        foreach ($act_ids as $id) {
            $result = ActAssets::make()->reconcileImages($id);
            $total_files += (int) $result['deleted_files'];
            $total_dirs += (int) $result['deleted_dirs'];
            $this->line(sprintf(
                '%s: удалено файлов %d, каталогов %d',
                $id,
                $result['deleted_files'],
                $result['deleted_dirs']
            ));
        }

        $this->info("Итого: файлов {$total_files}, каталогов {$total_dirs}");

        return self::SUCCESS;
    }
}
