<?php namespace Zen\Chub\Classes\System;

use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Models\Command;
use Throwable;

class CommandApp
{
    private static ?array $commands_cache = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Выполняет команду по её code.
     * 1) Читает локальный индекс code -> метаданные команды
     * 2) Если индекса нет, строит его из БД
     * 3) Выполняет только активные команды
     */
    public static function exec(string $code, ?array $input_data = null)
    {
        $command_code = trim($code);
        if ($command_code === '') {
            return null;
        }

        $command_meta = self::resolveCommandMeta($command_code);
        if (!$command_meta) {
            return null;
        }

        if (intval($command_meta['active'] ?? 0) !== 1) {
            return null;
        }

        return self::execCommandMeta($command_meta, $input_data);
    }

    public static function isCommandCode(string $code): ?string
    {
        if (!str_starts_with($code, 'run:')) {
            return null;
        }

        $command_code = trim(substr($code, 4));

        if (!$command_code) {
            return null;
        }

        return $command_code;
    }

    /**
     * Выполнение команды по экземпляру модели.
     */
    public static function execModel(Command $command, ?array $input_data = null)
    {
        if (!$command->exists) {
            return null;
        }

        if (!(bool) $command->active) {
            return null;
        }

        return self::execCommandMeta([
            'id' => (int) $command->id,
            'code' => (string) $command->code,
            'path' => 'plugins/zen/chub/data/commands/command_' . $command->id . '.php',
            'active' => (int) ((bool) $command->active),
            'count_enabled' => (int) ((bool) $command->count_enabled),
            'output_log' => (int) ((bool) $command->output_log),
            'api_enabled' => (int) ((bool) ($command->api_enabled ?? false)),
        ], $input_data);
    }

    /**
     * Единая точка выполнения исполняемого файла команды.
     */
    public static function execFile(string $command_file_path, ?string &$captured_output = null, ?array $input_data = null)
    {
        $file_path = trim($command_file_path);
        if ($file_path === '') {
            return null;
        }

        if (!file_exists($file_path)) {
            return null;
        }

        // Данные доступны внутри исполняемого кода как переменная $input_data.
        $input_data = is_array($input_data) ? $input_data : [];

        $use_output_buffer = func_num_args() >= 2;
        if (!$use_output_buffer) {
            return include $file_path;
        }

        ob_start();
        try {
            $result = include $file_path;
            $captured_output = (string) ob_get_clean();
            return $result;
        } catch (Throwable $exception) {
            $captured_output = (string) ob_get_clean();
            throw $exception;
        }
    }

    /**
     * Удаляет файл локального индекса команд.
     */
    public static function clearCache(): void
    {
        self::$commands_cache = null;

        $cache_file_path = self::getCacheFilePath();
        if (file_exists($cache_file_path)) {
            @unlink($cache_file_path);
        }
    }

    /**
     * Метаданные команды по коду (для HTTP API и прочих проверок).
     */
    public static function getResolvedCommandMeta(string $command_code): ?array
    {
        $command_code = trim($command_code);
        if ($command_code === '') {
            return null;
        }

        return self::resolveCommandMeta($command_code);
    }

    private static function resolveCommandMeta(string $command_code): ?array
    {
        $commands_map = self::getCommandsMap();
        $command_meta = $commands_map[$command_code] ?? null;

        if (is_array($command_meta)) {
            $relative_path = (string) ($command_meta['path'] ?? '');
            $file_path = base_path($relative_path);
            if (file_exists($file_path)) {
                return $command_meta;
            }
        }

        // Если индекс устарел — пересобираем его из БД и пробуем ещё раз.
        self::clearCache();
        $commands_map = self::getCommandsMap();
        $command_meta = $commands_map[$command_code] ?? null;

        if (!is_array($command_meta)) {
            return null;
        }

        return $command_meta;
    }

    private static function getCommandsMap(): array
    {
        if (self::$commands_cache !== null) {
            return self::$commands_cache;
        }

        $cache_file_path = self::getCacheFilePath();
        $cache_file_exists = file_exists($cache_file_path);
        $commands_map = Transformers::make()->arrayFromFile($cache_file_path);
        $cache_is_valid = true;
        if (is_array($commands_map)) {
            // Формат кэша должен быть: code => ['path' => '...', 'active' => 0|1]
            // Если найден legacy-формат, пересобираем кэш.
            foreach ($commands_map as $cache_item) {
                if (!is_array($cache_item)
                    || !array_key_exists('id', $cache_item)
                    || !array_key_exists('path', $cache_item)
                    || !array_key_exists('active', $cache_item)
                    || !array_key_exists('count_enabled', $cache_item)
                    || !array_key_exists('output_log', $cache_item)
                    || !array_key_exists('api_enabled', $cache_item)
                ) {
                    $commands_map = [];
                    $cache_is_valid = false;
                    break;
                }
            }
        }

        if ($cache_file_exists && is_array($commands_map) && $cache_is_valid) {
            self::$commands_cache = $commands_map;
            return $commands_map;
        }

        $commands_map = self::buildCommandsMapFromDb();
        self::storeCommandsMap($commands_map);
        self::$commands_cache = $commands_map;

        return $commands_map;
    }

    private static function storeCommandsMap(array $commands_map): void
    {
        $cache_file_path = self::getCacheFilePath();
        Files::make()->defineFilePath($cache_file_path);
        Transformers::make()->arrayToFile($commands_map, $cache_file_path);
    }

    /**
     * Формирует индекс code -> метаданные команды.
     * Для дублей code берется первая запись (минимальный id).
     */
    private static function buildCommandsMapFromDb(): array
    {
        $commands_map = [];

        $commands = Command::query()
            ->whereNotNull('code')
            ->orderBy('id')
            ->get(['id', 'code', 'active', 'count_enabled', 'output_log', 'api_enabled']);

        foreach ($commands as $command) {
            $command_code = trim((string) $command->code);
            if ($command_code === '') {
                continue;
            }

            if (array_key_exists($command_code, $commands_map)) {
                continue;
            }

            $commands_map[$command_code] = [
                'id' => (int) $command->id,
                'code' => $command_code,
                'path' => 'plugins/zen/chub/data/commands/command_' . $command->id . '.php',
                'active' => (int) ((bool) $command->active),
                'count_enabled' => (int) ((bool) $command->count_enabled),
                'output_log' => (int) ((bool) $command->output_log),
                'api_enabled' => (int) ((bool) ($command->api_enabled ?? false)),
            ];
        }

        return $commands_map;
    }

    private static function getCacheFilePath(): string
    {
        return base_path('plugins/zen/chub/data/commands/commands.json');
    }

    /**
     * Выполнение команды с учётом логирования.
     */
    private static function execCommandMeta(array $command_meta, ?array $input_data = null)
    {
        $command_file_relative_path = (string) ($command_meta['path'] ?? '');
        if ($command_file_relative_path === '') {
            return null;
        }

        $command_file_path = base_path($command_file_relative_path);
        $captured_output = '';

        try {
            $result = self::execFile($command_file_path, $captured_output, $input_data);
            self::writeCommandLog($command_meta, $result, $captured_output, null);
            return $result;
        } catch (Throwable $exception) {
            self::writeCommandLog($command_meta, null, $captured_output, $exception);
            throw $exception;
        }
    }

    /**
     * Запись лога выполнения (если count_enabled=1).
     */
    private static function writeCommandLog(array $command_meta, mixed $result, string $captured_output, ?Throwable $exception): void
    {
        if (intval($command_meta['count_enabled'] ?? 0) !== 1) {
            return;
        }

        $command_id = intval($command_meta['id'] ?? 0);
        if ($command_id <= 0) {
            return;
        }

        $db_path = self::getLogDbPathById($command_id);
        $sqlite = self::ensureLogBase($db_path);
        if (!$sqlite) {
            return;
        }

        $output_log_enabled = intval($command_meta['output_log'] ?? 0) === 1;
        $data_value = self::buildLogDataValue($output_log_enabled, $result, $captured_output, $exception);

        $sqlite->query('records')->insert([
            'data' => $data_value,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    private static function getLogDbPathById(int $command_id): string
    {
        return base_path('storage/chub/bases/command_log_' . $command_id . '.sqlite');
    }

    /**
     * Количество запусков команды по id (по данным SQLite-лога).
     */
    public static function getCallsCountByCommandId(int $command_id): int
    {
        if ($command_id <= 0) {
            return 0;
        }

        $db_path = self::getLogDbPathById($command_id);
        if (!file_exists($db_path)) {
            return 0;
        }

        try {
            $sqlite = Sqlite::connect($db_path);
            if (!$sqlite->tableExists('records')) {
                return 0;
            }

            return (int) $sqlite->query('records')->count();
        } catch (Throwable $exception) {
            return 0;
        }
    }

    private static function ensureLogBase(string $db_path): ?Sqlite
    {
        $dir_path = dirname($db_path);
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        $sqlite = file_exists($db_path)
            ? Sqlite::connect($db_path)
            : Sqlite::create($db_path);

        if (!$sqlite->tableExists('records')) {
            $sqlite->createTable('records', function ($table) {
                $table->id();
                $table->text('data')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        return $sqlite;
    }

    /**
     * Формирует значение для records.data:
     * - output_log=0 => пусто
     * - output_log=1 => только вывод кода (return/echo)
     */
    private static function buildLogDataValue(bool $output_log_enabled, mixed $result, string $captured_output, ?Throwable $exception): ?string
    {
        if (!$output_log_enabled) {
            return null;
        }

        if (is_string($result)) {
            return $result;
        }

        if (is_array($result)) {
            return Transformers::make()->toJson($result, false, true);
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if (is_int($result) || is_float($result)) {
            return (string) $result;
        }

        if ($captured_output !== '') {
            return $captured_output;
        }

        if ($result !== null) {
            $json_result = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json_result)) {
                return $json_result;
            }
        }

        if ($exception) {
            return $exception->getMessage();
        }

        return '';
    }
}