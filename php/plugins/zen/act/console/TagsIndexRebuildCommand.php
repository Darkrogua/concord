<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use RainLab\User\Models\User;
use Zen\Act\Classes\System\TagsApp;

class TagsIndexRebuildCommand extends Command
{
    protected $signature = 'act:tags-index-rebuild
        {--user= : Логин пользователя}
        {--dry-run : Только отчёт без записи}';

    protected $description = 'Пересобрать PostgreSQL-индекс тегов (zen_act_user_tags) и tags_cache.json';

    public function handle(): int
    {
        $login = trim((string) $this->option('user'));
        $dry_run = (bool) $this->option('dry-run');
        $tags = TagsApp::make();

        if ($login !== '') {
            if ($dry_run) {
                $this->line("dry-run: пользователь {$login}");
                return self::SUCCESS;
            }

            $stats = $tags->rebuildIndexForUser($login);
            $this->info("{$login}: актов {$stats['acts']}, строк индекса {$stats['tags']}");

            return self::SUCCESS;
        }

        if ($dry_run) {
            $users = User::query()->pluck('username')->filter()->map(fn ($u): string => (string) $u);
            foreach ($users as $username) {
                $this->line("dry-run: {$username}");
            }
            $this->info('Dry-run завершён');

            return self::SUCCESS;
        }

        $stats = $tags->rebuildAll();
        $this->info("Готово: пользователей {$stats['users']}, строк индекса {$stats['rows']}");

        return self::SUCCESS;
    }
}
