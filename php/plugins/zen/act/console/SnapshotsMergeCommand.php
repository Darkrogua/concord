<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\System\SnapshotApp;
use Zen\Act\Models\Act;

class SnapshotsMergeCommand extends Command
{
    protected $signature = 'act:snapshots-merge
        {--act= : UUID акта}
        {--from= : Номер версии «с» (1 = новейшая)}
        {--to= : Номер версии «по»}';

    protected $description = 'Слить диапазон версий акта в SQLite snapshots';

    public function handle(): int
    {
        $act_id = trim((string) $this->option('act'));
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        if ($act_id === '') {
            $this->error('Укажите --act=UUID');

            return self::FAILURE;
        }
        if ($from < 1 || $to < 1) {
            $this->error('from/to должны быть >= 1');

            return self::FAILURE;
        }

        $act = Act::find($act_id);
        if (! $act || $act->owner_id === null) {
            $this->error('Акт не найден или без owner_id');

            return self::FAILURE;
        }

        try {
            $result = SnapshotApp::make()->mergeRange($act_id, $from, $to, (int) $act->owner_id);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Слито: merged_id=%d, removed=%d, snapshots_count=%d',
            (int) ($result['merged_id'] ?? 0),
            (int) ($result['removed_count'] ?? 0),
            (int) ($result['snapshots_count'] ?? 0)
        ));

        return self::SUCCESS;
    }
}
