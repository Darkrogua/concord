<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\BasesApp;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Models\Base;

class BaseCommand extends Command
{
    private const MAX_QUERY_RESPONSE_CHARS = 24000;

    protected $signature = 'chub:base
        {mode : Режим работы, поддерживается только ai}
        {action? : AI-подкоманда}
        {--data= : JSON-объект входных данных}
        {--data-file= : Абсолютный путь до JSON-файла с входными данными}';

    protected $description = 'AI-управление сервисом SQLite-баз (Base/BasesApp)';

    private array $last_warnings = [];

    public function handle(): int
    {
        $mode = trim((string) $this->argument('mode'));
        $action = trim((string) $this->argument('action'));
        $this->last_warnings = [];

        if ($mode !== 'ai') {
            return $this->emitError(
                $action !== '' ? $action : 'unknown',
                'INVALID_MODE',
                'Поддерживается только режим ai. Используйте: php artisan chub:base ai <action>'
            );
        }

        if ($action === '') {
            return $this->emitError(
                'unknown',
                'UNKNOWN_ACTION',
                'Не указана AI-подкоманда. Используйте schema или list.'
            );
        }

        try {
            return match ($action) {
                'schema' => $this->handleAiSchema(),
                'list' => $this->handleAiList(),
                'upsert-base' => $this->handleAiUpsertBase(),
                'delete-base' => $this->handleAiDeleteBase(),
                'rebuild-schema' => $this->handleAiRebuildSchema(),
                'query' => $this->handleAiQuery(),
                'validate' => $this->handleAiValidate(),
                default => $this->emitError(
                    $action,
                    'UNKNOWN_ACTION',
                    "Неизвестная AI-подкоманда \"{$action}\""
                ),
            };
        } catch (\Throwable $exception) {
            return $this->emitError(
                $action,
                'RUNTIME_ERROR',
                $exception->getMessage()
            );
        }
    }

    private function handleAiSchema(): int
    {
        return $this->emitSuccess('schema', [
            'command' => 'php artisan chub:base ai <action> [--data=\'{}\'] [--data-file=/abs/path.json]',
            'actions' => [
                'schema' => [
                    'description' => 'Вернуть схему AI-интерфейса Base',
                    'input' => [],
                ],
                'list' => [
                    'description' => 'Список баз с полной schema, таблицами, колонками и количеством записей',
                    'input' => [],
                ],
                'upsert-base' => [
                    'description' => 'Создать или обновить Base по code',
                    'required' => ['code'],
                    'optional' => ['name', 'description', 'schema', 'preview_table', 'active', 'sort_order'],
                ],
                'delete-base' => [
                    'description' => 'Удалить Base по code (с удалением sqlite-файла)',
                    'required' => ['code'],
                ],
                'rebuild-schema' => [
                    'description' => 'Пересоздать sqlite-базу по schema',
                    'required' => ['code'],
                    'optional' => ['schema'],
                ],
                'query' => [
                    'description' => 'Выполнить SQL-запрос в sqlite базе и вернуть JSON-ответ',
                    'required' => ['code', 'sql'],
                ],
                'validate' => [
                    'description' => 'Проверка payload без изменений данных',
                    'required' => ['type'],
                    'allowed_type' => ['upsert-base', 'delete-base', 'rebuild-schema', 'query'],
                ],
            ],
            'query_output_limit' => [
                'max_chars' => self::MAX_QUERY_RESPONSE_CHARS,
                'message' => 'Объем ответа превышен, измените условия запроса для более компактного результата.',
            ],
        ]);
    }

    private function handleAiList(): int
    {
        $bases = Base::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($bases as $base) {
            $db_path = $base->getBasePath();
            $file_exists = file_exists($db_path);
            $file_size_bytes = $file_exists ? (int) (filesize($db_path) ?: 0) : null;

            $tables = [];
            if ($file_exists) {
                $tables = $this->describeTables($base);
            }

            $items[] = [
                'id' => (int) $base->id,
                'code' => (string) ($base->code ?? ''),
                'name' => (string) ($base->name ?? ''),
                'description' => (string) ($base->description ?? ''),
                'active' => (bool) ($base->active ?? false),
                'sort_order' => $base->sort_order !== null ? (int) $base->sort_order : null,
                'preview_table' => (string) ($base->preview_table ?? 'records'),
                'schema' => (string) ($base->schema ?? ''),
                'db_path' => $db_path,
                'file_exists' => $file_exists,
                'file_size_bytes' => $file_size_bytes,
                'tables' => $tables,
            ];
        }

        return $this->emitSuccess('list', ['items' => $items]);
    }

    private function handleAiUpsertBase(): int
    {
        $data = $this->readInputData();
        $code = $this->requireNonEmptyString($data, 'code');

        $base = Base::query()->where('code', $code)->first();
        $created = false;

        if (!$base) {
            $base = new Base();
            $base->code = $code;
            $created = true;
        }

        if (array_key_exists('name', $data)) {
            $base->name = $this->stringOrNull($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $base->description = $this->stringOrNull($data['description']);
        }
        if (array_key_exists('schema', $data)) {
            $base->schema = $this->stringOrNull($data['schema']);
        }
        if (array_key_exists('preview_table', $data)) {
            $base->preview_table = $this->stringOrNull($data['preview_table']);
        }
        if (array_key_exists('active', $data)) {
            $base->active = (bool) $data['active'];
        }
        if (array_key_exists('sort_order', $data)) {
            $base->sort_order = $this->intOrNull($data['sort_order']);
        }

        $base->save();

        return $this->emitSuccess('upsert-base', [
            'created' => $created,
            'base' => [
                'id' => (int) $base->id,
                'code' => (string) ($base->code ?? ''),
                'name' => (string) ($base->name ?? ''),
                'description' => (string) ($base->description ?? ''),
                'active' => (bool) ($base->active ?? false),
                'sort_order' => $base->sort_order !== null ? (int) $base->sort_order : null,
                'preview_table' => (string) ($base->preview_table ?? 'records'),
                'schema' => (string) ($base->schema ?? ''),
                'db_path' => $base->getBasePath(),
                'file_exists' => file_exists($base->getBasePath()),
            ],
        ]);
    }

    private function handleAiDeleteBase(): int
    {
        $data = $this->readInputData();
        $base = $this->findBaseByCode($data);
        $code = (string) ($base->code ?? '');
        $base->delete();

        return $this->emitSuccess('delete-base', [
            'code' => $code,
            'deleted' => true,
        ]);
    }

    private function handleAiRebuildSchema(): int
    {
        $data = $this->readInputData();
        $base = $this->findBaseByCode($data);

        if (array_key_exists('schema', $data)) {
            $base->schema = $this->stringOrNull($data['schema']);
            $base->save();
        }

        $base->clearBase();

        return $this->emitSuccess('rebuild-schema', [
            'code' => (string) ($base->code ?? ''),
            'rebuild' => true,
            'schema' => (string) ($base->schema ?? ''),
            'db_path' => $base->getBasePath(),
            'file_exists' => file_exists($base->getBasePath()),
        ]);
    }

    private function handleAiQuery(): int
    {
        $data = $this->readInputData();
        $base = $this->findBaseByCode($data);
        $code = (string) ($base->code ?? '');
        $sql = $this->requireNonEmptyString($data, 'sql');
        $bindings = $this->arrayFromOptionalKey($data, 'bindings');

        $bases_app = BasesApp::connect($code);
        $result = $bases_app->rawQuery($sql, $bindings);

        return $this->serializeQueryResultAndEmit($code, $sql, $bindings, $result);
    }

    private function handleAiValidate(): int
    {
        $data = $this->readInputData();
        $type = $this->requireNonEmptyString($data, 'type');
        $validated = ['type' => $type];

        if ($type === 'upsert-base') {
            $validated['code'] = $this->requireNonEmptyString($data, 'code');
            $validated['name'] = $this->stringOrNull($data['name'] ?? null);
            $validated['description'] = $this->stringOrNull($data['description'] ?? null);
            $validated['schema'] = $this->stringOrNull($data['schema'] ?? null);
            $validated['preview_table'] = $this->stringOrNull($data['preview_table'] ?? null);
            $validated['active'] = array_key_exists('active', $data) ? (bool) $data['active'] : null;
            $validated['sort_order'] = $this->intOrNull($data['sort_order'] ?? null);
        } elseif ($type === 'delete-base') {
            $validated['code'] = $this->requireNonEmptyString($data, 'code');
        } elseif ($type === 'rebuild-schema') {
            $validated['code'] = $this->requireNonEmptyString($data, 'code');
            $validated['schema'] = $this->stringOrNull($data['schema'] ?? null);
        } elseif ($type === 'query') {
            $validated['code'] = $this->requireNonEmptyString($data, 'code');
            $validated['sql'] = $this->requireNonEmptyString($data, 'sql');
            $validated['bindings'] = $this->arrayFromOptionalKey($data, 'bindings');
        } else {
            return $this->emitError(
                'validate',
                'VALIDATION_ERROR',
                'type должен быть одним из: upsert-base, delete-base, rebuild-schema, query'
            );
        }

        return $this->emitSuccess('validate', ['validated' => $validated]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function describeTables(Base $base): array
    {
        $sqlite = Sqlite::connect($base->getBasePath());
        $table_names = $sqlite->listTables();
        $tables = [];

        foreach ($table_names as $table_name) {
            $records_count = null;
            try {
                $records_count = (int) $base->sqliteQuery($table_name)->count();
            } catch (\Throwable $exception) {
                $records_count = null;
                $this->last_warnings[] = 'Не удалось получить count для таблицы ' . $table_name . ': ' . $exception->getMessage();
            }

            $tables[] = [
                'name' => (string) $table_name,
                'columns' => $sqlite->listFields((string) $table_name, true),
                'records_count' => $records_count,
            ];
        }

        return $tables;
    }

    private function serializeQueryResultAndEmit(string $code, string $sql, array $bindings, mixed $result): int
    {
        $query_type = 'statement';
        if (preg_match('/^\s*(select|pragma|with)\b/i', $sql) === 1) {
            $query_type = 'select';
        }

        if (is_array($result)) {
            $rows = [];
            $columns = [];

            foreach ($result as $row) {
                $row_data = (array) $row;
                $rows[] = $row_data;
                foreach (array_keys($row_data) as $column_name) {
                    $columns[(string) $column_name] = true;
                }
            }

            $normalized = [
                'code' => $code,
                'query_type' => $query_type,
                'sql' => $sql,
                'bindings' => $bindings,
                'rows_count' => count($rows),
                'columns' => array_values(array_keys($columns)),
                'rows' => $rows,
            ];

            $encoded = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rows_chars = is_string($encoded) ? strlen($encoded) : 0;
            if ($rows_chars > self::MAX_QUERY_RESPONSE_CHARS) {
                return $this->emitError(
                    'query',
                    'QUERY_RESULT_TOO_LARGE',
                    'Объем ответа превышен, измените условия запроса для более компактного результата.',
                    [
                        'max_chars' => self::MAX_QUERY_RESPONSE_CHARS,
                        'actual_chars' => $rows_chars,
                        'hint' => 'Используйте WHERE/LIMIT или более узкий SELECT.',
                    ]
                );
            }

            return $this->emitSuccess('query', $normalized);
        }

        return $this->emitSuccess('query', [
            'code' => $code,
            'query_type' => $query_type,
            'sql' => $sql,
            'bindings' => $bindings,
            'result' => $result,
        ]);
    }

    private function findBaseByCode(array $data): Base
    {
        $code = $this->requireNonEmptyString($data, 'code');
        $base = Base::query()->where('code', $code)->first();
        if (!$base) {
            throw new \RuntimeException("Base с code=\"{$code}\" не найден.");
        }

        return $base;
    }

    private function readInputData(): array
    {
        $raw_file_path = trim((string) $this->option('data-file'));
        if ($raw_file_path !== '') {
            if (!file_exists($raw_file_path)) {
                throw new \InvalidArgumentException("Файл данных не найден: {$raw_file_path}");
            }
            $raw = file_get_contents($raw_file_path);
            if ($raw === false) {
                throw new \InvalidArgumentException("Не удалось прочитать файл данных: {$raw_file_path}");
            }
        } else {
            $raw = (string) $this->option('data');
        }

        $decoded = Transformers::make()->fromJson($raw);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Ожидается валидный JSON-объект в --data или --data-file');
        }

        return $decoded;
    }

    private function requireNonEmptyString(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException("Поле {$key} обязательно и не должно быть пустым");
        }

        return $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string_value = trim((string) $value);
        return $string_value === '' ? null : $string_value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('sort_order должен быть числом');
        }

        return (int) $value;
    }

    /**
     * @return array<int,mixed>
     */
    private function arrayFromOptionalKey(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return [];
        }

        if (!is_array($data[$key])) {
            throw new \InvalidArgumentException("Поле {$key} должно быть массивом");
        }

        return $data[$key];
    }

    private function emitSuccess(string $action, array $data): int
    {
        return $this->emitJson([
            'ok' => true,
            'action' => $action,
            'data' => $data,
            'warnings' => $this->last_warnings,
            'errors' => [],
        ], self::SUCCESS);
    }

    private function emitError(string $action, string $code, string $message, ?array $details = null): int
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return $this->emitJson([
            'ok' => false,
            'action' => $action,
            'data' => null,
            'warnings' => $this->last_warnings,
            'errors' => [$error],
        ], self::FAILURE);
    }

    private function emitJson(array $payload, int $exit_code): int
    {
        $json = Transformers::make()->toJson($payload, true, true);
        $this->line($json ?? '{}');

        return $exit_code;
    }
}
