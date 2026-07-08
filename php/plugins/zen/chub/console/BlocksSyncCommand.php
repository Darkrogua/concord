<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\System\BlocksSync;

class BlocksSyncCommand extends Command
{
    protected $signature = 'chub:blocks:sync
        {--theme=liner : Код темы October}';

    protected $description = 'Синхронизация блоков темы: Vite auto-bundle SCSS/JS';

    public function handle(): int
    {
        $theme = (string) $this->option('theme');

        try {
            $result = (new BlocksSync($theme))->run();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Theme "%s": %d block(s), %d scss, %d js — auto-bundle updated (dom_id validated).',
            $theme,
            $result['blocks'],
            $result['scss'],
            $result['js']
        ));

        return self::SUCCESS;
    }
}
