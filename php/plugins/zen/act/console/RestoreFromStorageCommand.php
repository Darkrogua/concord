<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\System\ActApp;

class RestoreFromStorageCommand extends Command
{
    protected $signature = 'act:restore-from-storage
        {--dry-run : Проверить current.json без записи в БД}
        {--act= : UUID одного акта (по умолчанию — все каталоги с current.json)}';

    protected $description = 'Восстановить акты в PostgreSQL и SQLite из storage/acts/*/current.json';

    public function handle(): int
    {
        $dry_run = (bool) $this->option('dry-run');
        $act_id = $this->option('act');
        $act_id = is_string($act_id) && trim($act_id) !== '' ? trim($act_id) : null;

        $result = ActApp::make()->restoreFromStorage($act_id, $dry_run);

        foreach ($result['restored'] as $item) {
            $label = ($item['dry_run'] ?? false) ? 'OK (dry-run)' : (($item['created'] ?? false) ? 'создан' : 'обновлён');
            $this->line(sprintf(
                '  %s %s — %s, blocks: %d',
                $item['id'],
                $item['name'] ?? '',
                $label,
                (int) ($item['blocks_count'] ?? 0)
            ));
        }

        foreach ($result['failed'] as $item) {
            $this->error(sprintf('  %s — %s', $item['id'], $item['error']));
        }

        $this->info(sprintf(
            'Готово: restored=%d, failed=%d%s',
            (int) ($result['counts']['restored'] ?? 0),
            (int) ($result['counts']['failed'] ?? 0),
            $dry_run ? ' (dry-run)' : ''
        ));

        return ($result['counts']['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
