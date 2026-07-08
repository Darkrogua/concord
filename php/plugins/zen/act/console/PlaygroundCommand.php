<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use Zen\Act\Classes\Support\BlockWorkbench;

class PlaygroundCommand extends Command
{
    protected $signature = 'act:playground
        {action : pull|push|status|list}
        {--act= : UUID акта}
        {--block= : UUID блока webapp}
        {--force : push при расхождении block_hash с БД}
        {--allow-prod : разрешить на production (не рекомендуется)}';

    protected $description = 'Локальный pull/push HTML webapp-блоков в playground/';

    public function handle(): int
    {
        if (config('app.env') === 'production' && ! $this->option('allow-prod')) {
            $this->error('act:playground только для local dev. Используйте --allow-prod осознанно на проде.');

            return self::FAILURE;
        }

        $action = trim((string) $this->argument('action'));
        $workbench = BlockWorkbench::make();

        try {
            return match ($action) {
                'pull' => $this->handlePull($workbench),
                'push' => $this->handlePush($workbench),
                'status' => $this->handleStatus($workbench),
                'list' => $this->handleList($workbench),
                default => $this->unknownAction($action),
            };
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function handlePull(BlockWorkbench $workbench): int
    {
        [$act_id, $block_id] = $this->requireActAndBlock();

        $result = $workbench->pull($act_id, $block_id);

        $this->info(sprintf(
            'pull OK: %s (%d bytes)',
            $result['fragment_path'],
            (int) $result['bytes']
        ));
        $this->line('block_hash: '.$result['block_hash']);

        return self::SUCCESS;
    }

    private function handlePush(BlockWorkbench $workbench): int
    {
        [$act_id, $block_id] = $this->requireActAndBlock();
        $force = (bool) $this->option('force');

        $result = $workbench->push($act_id, $block_id, $force);

        if ($result['skipped'] ?? false) {
            $this->info('push пропущен: '.($result['reason'] ?? 'нет изменений'));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'push OK: block_hash=%s (%d bytes)%s',
            $result['block_hash'],
            (int) $result['bytes'],
            ($result['forced'] ?? false) ? ' [force]' : ''
        ));

        return self::SUCCESS;
    }

    private function handleStatus(BlockWorkbench $workbench): int
    {
        [$act_id, $block_id] = $this->requireActAndBlock();

        $status = $workbench->status($act_id, $block_id);

        $this->line('directory: '.$status['directory']);
        $this->line('disk: '.($status['disk_exists'] ? 'yes' : 'no'));
        $this->line('db: '.($status['db_exists'] ? 'yes' : 'no'));
        $this->line('live block_hash: '.($status['live_block_hash'] ?? '—'));
        $this->line('meta block_hash: '.($status['meta_block_hash'] ?? '—'));
        $this->line('hash in sync: '.($status['hash_in_sync'] ? 'yes' : 'no'));
        $this->line('content changed: '.($status['content_changed'] ? 'yes' : 'no'));
        $this->line('push ready: '.($status['push_ready'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function handleList(BlockWorkbench $workbench): int
    {
        $entries = $workbench->listEntries();

        if ($entries === []) {
            $this->info('playground пуст: '.$workbench->playgroundRoot());

            return self::SUCCESS;
        }

        foreach ($entries as $entry) {
            $this->line(sprintf(
                '  %s / %s — %s (%s)',
                $entry['act_id'],
                $entry['block_id'],
                $entry['name'] !== '' ? $entry['name'] : 'webapp',
                $entry['pulled_at']
            ));
        }

        $this->info('Всего: '.count($entries));

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function requireActAndBlock(): array
    {
        $act_id = trim((string) $this->option('act'));
        $block_id = trim((string) $this->option('block'));

        if ($act_id === '' || $block_id === '') {
            throw new \InvalidArgumentException('Укажите --act=UUID и --block=UUID');
        }

        return [$act_id, $block_id];
    }

    private function unknownAction(string $action): int
    {
        $this->error("Неизвестное действие \"{$action}\". Используйте: pull, push, status, list");

        return self::FAILURE;
    }
}
