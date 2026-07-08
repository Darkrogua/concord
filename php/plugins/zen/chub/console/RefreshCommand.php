<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;

/**
 * Refresh Command
 *
 * @link https://docs.octobercms.com/4.x/extend/console-commands.html
 */
class RefreshCommand extends Command
{
    /**
     * @var string signature for the console command.
     */
    protected $signature = 'chub:refresh {migration_file}';

    /**
     * @var string description is the console command description
     */
    protected $description = 'Перезапуск выбранной миграции плагина Zen.Chub';

    /**
     * handle executes the console command.
     */
    public function handle()
    {
        $migration_file = $this->argument('migration_file');
        $result = self::restartMigration($migration_file);

        foreach ($result['messages'] as $message) {
            $this->info($message);
        }

        if (!$result['ok']) {
            $this->error($result['error']);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Статический запуск перезапуска миграции (down + up), можно вызывать откуда угодно.
     */
    public static function restartMigration(string $migration_file): array
    {
        $messages = [];
        $updates_path = plugins_path('zen/chub/updates');
        $migration_path = $updates_path . DIRECTORY_SEPARATOR . $migration_file;

        if (!is_file($migration_path)) {
            return [
                'ok' => false,
                'messages' => $messages,
                'error' => "Файл миграции не найден: {$migration_file}",
            ];
        }

        $migration_class = self::resolveMigrationClass($migration_file);

        if ($migration_class === null) {
            return [
                'ok' => false,
                'messages' => $messages,
                'error' => "Не удалось определить класс миграции для файла: {$migration_file}",
            ];
        }

        require_once $migration_path;

        if (!class_exists($migration_class)) {
            return [
                'ok' => false,
                'messages' => $messages,
                'error' => "Класс миграции не найден: {$migration_class}",
            ];
        }

        $migration_instance = new $migration_class();

        if (!method_exists($migration_instance, 'down') || !method_exists($migration_instance, 'up')) {
            return [
                'ok' => false,
                'messages' => $messages,
                'error' => "Миграция {$migration_class} должна содержать методы down() и up()",
            ];
        }

        $messages[] = "Выполняю down(): {$migration_class}";
        $migration_instance->down();

        $messages[] = "Выполняю up(): {$migration_class}";
        $migration_instance->up();

        $messages[] = "Миграция успешно перезапущена: {$migration_file}";

        return [
            'ok' => true,
            'messages' => $messages,
            'error' => null,
        ];
    }

    protected static function resolveMigrationClass(string $migration_file): ?string
    {
        $migration_name = preg_replace('/\.php$/', '', $migration_file);

        if (empty($migration_name)) {
            return null;
        }

        $class_name = str_replace(' ', '', ucwords(str_replace('_', ' ', $migration_name)));

        return "Zen\\Chub\\Updates\\{$class_name}";
    }
}
