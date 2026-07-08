<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\ProcessApp;
use Zen\Chub\Models\Flow;

class FlowCommand extends Command
{
    protected $signature = 'chub:flow
        {mode : Режим работы, поддерживается только ai}
        {action? : AI-подкоманда}
        {--data= : JSON-объект входных данных}
        {--data-file= : Абсолютный путь до JSON-файла с входными данными}';

    protected $description = 'AI-управление сущностью Flow и процессами Stream';

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
                'Поддерживается только режим ai. Используйте: php artisan chub:flow ai <action>'
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
                'upsert-flow' => $this->handleAiUpsertFlow(),
                'upsert-process' => $this->handleAiUpsertProcess(),
                'delete-process' => $this->handleAiDeleteProcess(),
                'move-process' => $this->handleAiMoveProcess(),
                'run-process' => $this->handleAiRunProcess(),
                'kill-process' => $this->handleAiKillProcess(),
                'clear-process' => $this->handleAiClearProcess(),
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
            'command' => 'php artisan chub:flow ai <action> [--data=\'{}\'] [--data-file=/abs/path.json]',
            'actions' => [
                'schema' => [
                    'description' => 'Вернуть схему AI-интерфейса Flow',
                    'input' => [],
                ],
                'list' => [
                    'description' => 'Список flow + процессы + активные хуки + runtime state',
                    'input' => [],
                ],
                'upsert-flow' => [
                    'description' => 'Создать или обновить Flow по code',
                    'required' => ['code'],
                    'optional' => ['name', 'description', 'sort_order'],
                ],
                'upsert-process' => [
                    'description' => 'Создать или обновить процесс в Flow по process.code',
                    'required' => ['flow_code', 'process.code'],
                    'optional' => ['process.name', 'process.active', 'process.hooks', 'process.handler_*'],
                ],
                'delete-process' => [
                    'description' => 'Удалить процесс из Flow',
                    'required' => ['flow_code', 'process_code'],
                ],
                'move-process' => [
                    'description' => 'Переместить процесс внутри Flow',
                    'required' => ['flow_code', 'process_code', 'direction'],
                    'allowed_direction' => ['up', 'down'],
                ],
                'run-process' => [
                    'description' => 'Запустить процесс',
                    'required' => ['flow_code', 'process_code'],
                ],
                'kill-process' => [
                    'description' => 'Остановить процесс (без очистки state)',
                    'required' => ['flow_code', 'process_code'],
                ],
                'clear-process' => [
                    'description' => 'Остановить процесс и очистить state',
                    'required' => ['flow_code', 'process_code'],
                ],
                'validate' => [
                    'description' => 'Проверка payload без изменений данных',
                    'required' => ['type'],
                    'allowed_type' => [
                        'upsert-flow',
                        'upsert-process',
                        'delete-process',
                        'move-process',
                        'run-process',
                        'kill-process',
                        'clear-process',
                    ],
                ],
            ],
            'hooks_format' => [
                'main' => ['path' => 'handler_path', 'source' => 'handler_path_source', 'target' => 'handler_path_target'],
                'start' => ['path' => 'handler_start_path', 'source' => 'handler_start_path_source', 'target' => 'handler_start_path_target'],
                'finish' => ['path' => 'handler_finish_path', 'source' => 'handler_finish_path_source', 'target' => 'handler_finish_path_target'],
                'error' => ['path' => 'handler_error_path', 'source' => 'handler_error_path_source', 'target' => 'handler_error_path_target'],
                'stop' => ['path' => 'handler_stop_path', 'source' => 'handler_stop_path_source', 'target' => 'handler_stop_path_target'],
            ],
        ]);
    }

    private function handleAiList(): int
    {
        $flows = Flow::query()
            ->where(function ($query) {
                $query->where('is_folder', 0)->orWhereNull('is_folder');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($flows as $flow) {
            $process_app = ProcessApp::make($flow);
            $flow_data = $process_app->getFlowData();
            $flow_states = $process_app->getFlowStates();
            $states_map = [];
            foreach ($flow_states as $state) {
                $state_code = (string) ($state['code'] ?? '');
                if ($state_code !== '') {
                    $states_map[$state_code] = $state;
                }
            }

            $processes = [];
            foreach ($flow_data as $index => $process) {
                if (!is_array($process)) {
                    continue;
                }
                $processes[] = $this->serializeProcess($process, $states_map, $index);
            }

            $items[] = [
                'id' => (int) $flow->id,
                'code' => (string) ($flow->code ?? ''),
                'name' => (string) ($flow->name ?? ''),
                'description' => (string) ($flow->description ?? ''),
                'sort_order' => $flow->sort_order !== null ? (int) $flow->sort_order : null,
                'processes' => $processes,
            ];
        }

        return $this->emitSuccess('list', ['items' => $items]);
    }

    private function handleAiUpsertFlow(): int
    {
        $data = $this->readInputData();
        $code = $this->requireNonEmptyString($data, 'code');

        $flow = Flow::query()
            ->where('code', $code)
            ->where(function ($query) {
                $query->where('is_folder', 0)->orWhereNull('is_folder');
            })
            ->first();
        $created = false;

        if (!$flow) {
            if (Flow::query()->where('code', $code)->where('is_folder', 1)->exists()) {
                throw new \RuntimeException(
                    "Код \"{$code}\" занят папкой в менеджере потоков; для потока нужен другой code."
                );
            }

            $flow = new Flow();
            $flow->code = $code;
            $flow->is_folder = 0;
            $created = true;
        }

        if (array_key_exists('name', $data)) {
            $flow->name = $this->stringOrNull($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $flow->description = $this->stringOrNull($data['description']);
        }
        if (array_key_exists('sort_order', $data)) {
            $flow->sort_order = $this->intOrNull($data['sort_order']);
        }

        $flow->save();

        return $this->emitSuccess('upsert-flow', [
            'created' => $created,
            'flow' => [
                'id' => (int) $flow->id,
                'code' => (string) $flow->code,
                'name' => (string) ($flow->name ?? ''),
                'description' => (string) ($flow->description ?? ''),
                'sort_order' => $flow->sort_order !== null ? (int) $flow->sort_order : null,
            ],
        ]);
    }

    private function handleAiUpsertProcess(): int
    {
        $data = $this->readInputData();
        $flow = $this->findFlowByCode($data);
        $process_payload = $this->requireArray($data, 'process');
        $process_code = $this->requireNonEmptyString($process_payload, 'code');

        $process_app = ProcessApp::make($flow);
        $flow_data = $process_app->getFlowData();
        $process_index = $this->findProcessIndex($flow_data, $process_code);
        $existing_process = $process_index !== null && is_array($flow_data[$process_index] ?? null)
            ? $flow_data[$process_index]
            : [];

        $normalized_process = $this->normalizeProcessPayload($process_payload, $existing_process);
        $normalized_process['code'] = $process_code;
        if ($normalized_process['name'] === null) {
            $normalized_process['name'] = $existing_process['name'] ?? $process_code;
        }
        if (!array_key_exists('active', $normalized_process) || $normalized_process['active'] === null) {
            $normalized_process['active'] = array_key_exists('active', $existing_process)
                ? (bool) $existing_process['active']
                : true;
        }

        $created = false;
        if ($process_index === null) {
            $flow_data[] = $normalized_process;
            $process_index = array_key_last($flow_data);
            $created = true;
        } else {
            $flow_data[$process_index] = array_merge($existing_process, $normalized_process);
        }

        $process_app->updateFlow($flow_data);
        $saved_flow_data = $process_app->getFlowData();
        $saved_process = is_array($saved_flow_data[$process_index] ?? null) ? $saved_flow_data[$process_index] : $normalized_process;

        return $this->emitSuccess('upsert-process', [
            'created' => $created,
            'flow_code' => (string) $flow->code,
            'process' => $this->serializeProcess($saved_process, [], $process_index),
        ]);
    }

    private function handleAiDeleteProcess(): int
    {
        $data = $this->readInputData();
        $flow = $this->findFlowByCode($data);
        $process_code = $this->requireNonEmptyString($data, 'process_code');
        $process_app = ProcessApp::make($flow);
        $flow_data = $process_app->getFlowData();
        $process_index = $this->findProcessIndex($flow_data, $process_code);
        if ($process_index === null) {
            return $this->emitError(
                'delete-process',
                'PROCESS_NOT_FOUND',
                "Процесс с code=\"{$process_code}\" не найден в flow \"{$flow->code}\"."
            );
        }

        $process_app->deleteProcess($process_index);

        return $this->emitSuccess('delete-process', [
            'flow_code' => (string) $flow->code,
            'process_code' => $process_code,
            'deleted' => true,
        ]);
    }

    private function handleAiMoveProcess(): int
    {
        $data = $this->readInputData();
        $flow = $this->findFlowByCode($data);
        $process_code = $this->requireNonEmptyString($data, 'process_code');
        $direction = $this->requireNonEmptyString($data, 'direction');
        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->emitError(
                'move-process',
                'INVALID_DIRECTION',
                'direction должен быть up или down'
            );
        }

        $process_app = ProcessApp::make($flow);
        $flow_data = $process_app->getFlowData();
        if ($this->findProcessIndex($flow_data, $process_code) === null) {
            return $this->emitError(
                'move-process',
                'PROCESS_NOT_FOUND',
                "Процесс с code=\"{$process_code}\" не найден в flow \"{$flow->code}\"."
            );
        }

        $process_app->moveProcess($process_code, $direction);

        return $this->emitSuccess('move-process', [
            'flow_code' => (string) $flow->code,
            'process_code' => $process_code,
            'direction' => $direction,
            'moved' => true,
        ]);
    }

    private function handleAiRunProcess(): int
    {
        return $this->executeRuntimeAction('run-process');
    }

    private function handleAiKillProcess(): int
    {
        return $this->executeRuntimeAction('kill-process');
    }

    private function handleAiClearProcess(): int
    {
        return $this->executeRuntimeAction('clear-process');
    }

    private function executeRuntimeAction(string $action): int
    {
        $data = $this->readInputData();
        $flow = $this->findFlowByCode($data);
        $process_code = $this->requireNonEmptyString($data, 'process_code');
        $process_app = ProcessApp::make($flow);
        $flow_data = $process_app->getFlowData();
        if ($this->findProcessIndex($flow_data, $process_code) === null) {
            return $this->emitError(
                $action,
                'PROCESS_NOT_FOUND',
                "Процесс с code=\"{$process_code}\" не найден в flow \"{$flow->code}\"."
            );
        }

        if ($action === 'run-process') {
            $process_app->runProcess($process_code);
        } elseif ($action === 'kill-process') {
            $process_app->killProcess($process_code);
        } else {
            $process_app->killProcess($process_code, true);
        }

        return $this->emitSuccess($action, [
            'flow_code' => (string) $flow->code,
            'process_code' => $process_code,
            'done' => true,
        ]);
    }

    private function handleAiValidate(): int
    {
        $data = $this->readInputData();
        $type = $this->requireNonEmptyString($data, 'type');
        $validated = ['type' => $type];

        if ($type === 'upsert-flow') {
            $validated['code'] = $this->requireNonEmptyString($data, 'code');
            $validated['name'] = $this->stringOrNull($data['name'] ?? null);
            $validated['description'] = $this->stringOrNull($data['description'] ?? null);
            $validated['sort_order'] = $this->intOrNull($data['sort_order'] ?? null);
        } elseif ($type === 'upsert-process') {
            $validated['flow_code'] = $this->requireNonEmptyString($data, 'flow_code');
            $process_payload = $this->requireArray($data, 'process');
            $process_code = $this->requireNonEmptyString($process_payload, 'code');
            $validated['process'] = $this->normalizeProcessPayload(
                array_merge($process_payload, ['code' => $process_code]),
                []
            );
        } elseif (in_array($type, ['delete-process', 'run-process', 'kill-process', 'clear-process'], true)) {
            $validated['flow_code'] = $this->requireNonEmptyString($data, 'flow_code');
            $validated['process_code'] = $this->requireNonEmptyString($data, 'process_code');
        } elseif ($type === 'move-process') {
            $validated['flow_code'] = $this->requireNonEmptyString($data, 'flow_code');
            $validated['process_code'] = $this->requireNonEmptyString($data, 'process_code');
            $direction = $this->requireNonEmptyString($data, 'direction');
            if (!in_array($direction, ['up', 'down'], true)) {
                throw new \InvalidArgumentException('direction должен быть up или down');
            }
            $validated['direction'] = $direction;
        } else {
            return $this->emitError(
                'validate',
                'VALIDATION_ERROR',
                'type должен быть одним из: upsert-flow, upsert-process, delete-process, move-process, run-process, kill-process, clear-process'
            );
        }

        return $this->emitSuccess('validate', ['validated' => $validated]);
    }

    private function serializeProcess(array $process, array $states_map, int $index): array
    {
        $process_code = (string) ($process['code'] ?? '');
        $hooks = $this->extractHooksFromProcess($process);
        $active_hooks = [];
        foreach ($hooks as $hook_name => $hook_payload) {
            if (($hook_payload['path'] ?? null) !== null) {
                $active_hooks[$hook_name] = $hook_payload;
            }
        }

        return [
            'index' => $index,
            'code' => $process_code,
            'name' => $this->stringOrNull($process['name'] ?? null),
            'active' => array_key_exists('active', $process) ? (bool) $process['active'] : true,
            'runnable' => ($hooks['main']['path'] ?? null) !== null,
            'hooks' => $hooks,
            'active_hooks' => $active_hooks,
            'state' => $process_code !== '' ? ($states_map[$process_code] ?? null) : null,
            'raw_process' => $process,
        ];
    }

    private function extractHooksFromProcess(array $process): array
    {
        return [
            'main' => [
                'path' => $this->stringOrNull($process['handler_path'] ?? null),
                'source' => $this->stringOrNull($process['handler_path_source'] ?? null),
                'target' => $this->stringOrNull($process['handler_path_target'] ?? null),
            ],
            'start' => [
                'path' => $this->stringOrNull($process['handler_start_path'] ?? null),
                'source' => $this->stringOrNull($process['handler_start_path_source'] ?? null),
                'target' => $this->stringOrNull($process['handler_start_path_target'] ?? null),
            ],
            'finish' => [
                'path' => $this->stringOrNull($process['handler_finish_path'] ?? null),
                'source' => $this->stringOrNull($process['handler_finish_path_source'] ?? null),
                'target' => $this->stringOrNull($process['handler_finish_path_target'] ?? null),
            ],
            'error' => [
                'path' => $this->stringOrNull($process['handler_error_path'] ?? null),
                'source' => $this->stringOrNull($process['handler_error_path_source'] ?? null),
                'target' => $this->stringOrNull($process['handler_error_path_target'] ?? null),
            ],
            'stop' => [
                'path' => $this->stringOrNull($process['handler_stop_path'] ?? null),
                'source' => $this->stringOrNull($process['handler_stop_path_source'] ?? null),
                'target' => $this->stringOrNull($process['handler_stop_path_target'] ?? null),
            ],
        ];
    }

    private function normalizeProcessPayload(array $process_payload, array $base_process): array
    {
        $normalized = $base_process;
        if (array_key_exists('name', $process_payload)) {
            $normalized['name'] = $this->stringOrNull($process_payload['name']);
        }
        if (array_key_exists('active', $process_payload)) {
            $normalized['active'] = (bool) $process_payload['active'];
        }

        $legacy_handler_keys = [
            'handler_path',
            'handler_path_source',
            'handler_path_target',
            'handler_start_path',
            'handler_start_path_source',
            'handler_start_path_target',
            'handler_finish_path',
            'handler_finish_path_source',
            'handler_finish_path_target',
            'handler_error_path',
            'handler_error_path_source',
            'handler_error_path_target',
            'handler_stop_path',
            'handler_stop_path_source',
            'handler_stop_path_target',
        ];
        foreach ($legacy_handler_keys as $legacy_key) {
            if (array_key_exists($legacy_key, $process_payload)) {
                $normalized[$legacy_key] = $this->stringOrNull($process_payload[$legacy_key]);
            }
        }

        $hooks_payload = [];
        if (array_key_exists('hooks', $process_payload)) {
            $hooks_payload = $this->requireArray($process_payload, 'hooks');
        }

        $hook_mappings = [
            'main' => 'handler_path',
            'start' => 'handler_start_path',
            'finish' => 'handler_finish_path',
            'error' => 'handler_error_path',
            'stop' => 'handler_stop_path',
        ];
        foreach ($hook_mappings as $hook_name => $handler_field) {
            if (!array_key_exists($hook_name, $hooks_payload)) {
                continue;
            }

            $hook_data = $hooks_payload[$hook_name];
            if (!is_array($hook_data)) {
                throw new \InvalidArgumentException("hooks.{$hook_name} должен быть объектом");
            }

            if (array_key_exists('path', $hook_data)) {
                $normalized[$handler_field] = $this->stringOrNull($hook_data['path']);
            }
            if (array_key_exists('source', $hook_data)) {
                $normalized[$handler_field . '_source'] = $this->stringOrNull($hook_data['source']);
            }
            if (array_key_exists('target', $hook_data)) {
                $normalized[$handler_field . '_target'] = $this->stringOrNull($hook_data['target']);
            }
        }

        return $normalized;
    }

    private function findProcessIndex(array $flow_data, string $process_code): ?int
    {
        foreach ($flow_data as $index => $process) {
            if (!is_array($process)) {
                continue;
            }
            $code = trim((string) ($process['code'] ?? ''));
            if ($code === $process_code) {
                return $index;
            }
        }

        return null;
    }

    private function findFlowByCode(array $data): Flow
    {
        $flow_code = $this->requireNonEmptyString($data, 'flow_code');
        $flow = Flow::query()
            ->where('code', $flow_code)
            ->where(function ($query) {
                $query->where('is_folder', 0)->orWhereNull('is_folder');
            })
            ->first();
        if (!$flow) {
            throw new \RuntimeException("Flow с code=\"{$flow_code}\" не найден.");
        }

        return $flow;
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

    private function requireArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Поле {$key} должно быть объектом");
        }

        return $value;
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
