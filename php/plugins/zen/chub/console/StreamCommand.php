<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Stream;

class StreamCommand extends Command
{
    protected $signature = 'chub:stream
        {mode : Режим работы: process или ai}
        {action? : AI-подкоманда}
        {--uid= : UID потока (для process и runtime AI-действий)}
        {--data= : JSON-объект входных данных}
        {--data-file= : Абсолютный путь до JSON-файла с входными данными}';

    protected $description = 'Управление потоками Stream (runtime + AI-интерфейс)';

    private array $last_warnings = [];

    public function handle(): int
    {
        $mode = trim((string) $this->argument('mode'));
        $action = trim((string) $this->argument('action'));
        $this->last_warnings = [];

        if ($mode === 'process') {
            return $this->handleProcessMode();
        }

        if ($mode !== 'ai') {
            return $this->emitError(
                $action !== '' ? $action : 'unknown',
                'INVALID_MODE',
                'Поддерживается mode=process или mode=ai. Пример: php artisan chub:stream ai schema'
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
                'state' => $this->handleAiState(),
                'run' => $this->handleAiRun(),
                'stop' => $this->handleAiStop(),
                'resume' => $this->handleAiResume(),
                'kill' => $this->handleAiKill(),
                'clear' => $this->handleAiClear(),
                'recreate' => $this->handleAiRecreate(),
                'run-process' => $this->handleAiRunProcess(),
                'clear-storage' => $this->handleAiClearStorage(),
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

    private function handleProcessMode(): int
    {
        $uid = trim((string) $this->option('uid'));
        if ($uid === '') {
            $this->line('process: uid is empty');
            return self::SUCCESS;
        }

        Stream::connect($uid)->streamProcessInit();
        return self::SUCCESS;
    }

    private function handleAiSchema(): int
    {
        return $this->emitSuccess('schema', [
            'command' => 'php artisan chub:stream ai <action> [--data=\'{}\'] [--data-file=/abs/path.json]',
            'actions' => [
                'schema' => [
                    'description' => 'Вернуть схему AI-интерфейса Stream',
                    'input' => [],
                ],
                'list' => [
                    'description' => 'Список state-файлов потоков с runtime-статусом',
                    'input' => [],
                ],
                'state' => [
                    'description' => 'Подробный runtime-state потока',
                    'required' => ['uid'],
                ],
                'run' => [
                    'description' => 'Запустить поток в фоне через streamRun()',
                    'required' => ['uid'],
                ],
                'stop' => [
                    'description' => 'Мягкая остановка потока (command_stop=true)',
                    'required' => ['uid'],
                ],
                'resume' => [
                    'description' => 'Возобновить ранее остановленный поток',
                    'required' => ['uid'],
                ],
                'kill' => [
                    'description' => 'Принудительно завершить процесс по PID из state',
                    'required' => ['uid'],
                ],
                'clear' => [
                    'description' => 'Очистить state-файлы потока',
                    'required' => ['uid'],
                ],
                'recreate' => [
                    'description' => 'Пересоздать state потока',
                    'required' => ['uid'],
                ],
                'run-process' => [
                    'description' => 'Выполнить streamProcessInit() в текущем процессе',
                    'required' => ['uid'],
                ],
                'clear-storage' => [
                    'description' => 'Очистить все state-файлы (только с force=true)',
                    'required' => ['force=true'],
                ],
                'validate' => [
                    'description' => 'Проверка payload без изменений данных',
                    'required' => ['type'],
                    'allowed_type' => [
                        'state',
                        'run',
                        'stop',
                        'resume',
                        'kill',
                        'clear',
                        'recreate',
                        'run-process',
                        'clear-storage',
                    ],
                ],
            ],
        ]);
    }

    private function handleAiList(): int
    {
        $items = [];
        foreach ($this->listStreamStateFiles() as $file_path) {
            $uid = basename($file_path, '.json');
            $stream = Stream::connect($uid);
            $state = $stream->getStateDataFresh();
            if (!is_array($state)) {
                continue;
            }
            $items[] = $this->serializeState($uid, $stream, $state);
        }

        usort($items, function (array $left, array $right): int {
            return strcmp((string) ($left['uid'] ?? ''), (string) ($right['uid'] ?? ''));
        });

        return $this->emitSuccess('list', [
            'states_dir' => $this->getStatesDirPath(),
            'items' => $items,
        ]);
    }

    private function handleAiState(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $state = $stream->getStateDataFresh();

        if (!is_array($state)) {
            return $this->emitError('state', 'STATE_NOT_FOUND', "Состояние потока uid=\"{$uid}\" не найдено.");
        }

        return $this->emitSuccess('state', $this->serializeState($uid, $stream, $state));
    }

    private function handleAiRun(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $stream->streamRun();

        return $this->emitSuccess('run', [
            'uid' => $uid,
            'done' => true,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiStop(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $stream->stopStream();

        return $this->emitSuccess('stop', [
            'uid' => $uid,
            'done' => true,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiResume(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $stream->resumeStream();

        return $this->emitSuccess('resume', [
            'uid' => $uid,
            'done' => true,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiKill(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $killed = $stream->killStream();

        return $this->emitSuccess('kill', [
            'uid' => $uid,
            'killed' => $killed,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiClear(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        Stream::clear($uid);

        return $this->emitSuccess('clear', [
            'uid' => $uid,
            'done' => true,
            'exists_after_clear' => Stream::exists($uid),
        ]);
    }

    private function handleAiRecreate(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);

        if (Stream::exists($uid)) {
            $stream = Stream::connect($uid)->recreate();
        } else {
            $stream = Stream::create($uid);
        }

        return $this->emitSuccess('recreate', [
            'uid' => $uid,
            'done' => true,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiRunProcess(): int
    {
        $data = $this->readInputData();
        $uid = $this->extractUid($data);
        $stream = $this->requireExistingStream($uid);
        $stream->streamProcessInit();

        return $this->emitSuccess('run-process', [
            'uid' => $uid,
            'done' => true,
            'state' => $this->serializeState($uid, $stream, $stream->getStateDataFresh()),
        ]);
    }

    private function handleAiClearStorage(): int
    {
        $data = $this->readInputData();
        $force = (bool) ($data['force'] ?? false);
        if (!$force) {
            return $this->emitError(
                'clear-storage',
                'VALIDATION_ERROR',
                'Для clear-storage требуется force=true'
            );
        }

        Stream::clearStateStorage();
        return $this->emitSuccess('clear-storage', [
            'done' => true,
            'states_dir' => $this->getStatesDirPath(),
        ]);
    }

    private function handleAiValidate(): int
    {
        $data = $this->readInputData();
        $type = $this->requireNonEmptyString($data, 'type');
        $validated = ['type' => $type];

        if (in_array($type, ['state', 'run', 'stop', 'resume', 'kill', 'clear', 'recreate', 'run-process'], true)) {
            $validated['uid'] = $this->extractUid($data);
        } elseif ($type === 'clear-storage') {
            $validated['force'] = (bool) ($data['force'] ?? false);
            if (!$validated['force']) {
                throw new \InvalidArgumentException('Для clear-storage требуется force=true');
            }
        } else {
            return $this->emitError(
                'validate',
                'VALIDATION_ERROR',
                'type должен быть одним из: state, run, stop, resume, kill, clear, recreate, run-process, clear-storage'
            );
        }

        return $this->emitSuccess('validate', ['validated' => $validated]);
    }

    private function requireExistingStream(string $uid): Stream
    {
        if (!Stream::exists($uid)) {
            throw new \RuntimeException("Состояние потока uid=\"{$uid}\" не найдено.");
        }

        return Stream::connect($uid);
    }

    /**
     * @return array<int,string>
     */
    private function listStreamStateFiles(): array
    {
        $states_dir = $this->getStatesDirPath();
        if (!is_dir($states_dir)) {
            return [];
        }

        $files = glob($states_dir . '/*.json') ?: [];
        $state_files = [];

        foreach ($files as $file_path) {
            if (!is_file($file_path)) {
                continue;
            }

            // Исключаем batch-файлы вида <uid>.<number>.json
            if (preg_match('/\.\d+\.json$/', $file_path) === 1) {
                continue;
            }

            $state_files[] = $file_path;
        }

        return $state_files;
    }

    private function getStatesDirPath(): string
    {
        return base_path('storage/chub/states');
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function serializeState(string $uid, Stream $stream, array $state): array
    {
        return [
            'uid' => $uid,
            'exists' => Stream::exists($uid),
            'in_process' => $stream->inProcess(),
            'completed' => $stream->isCompleted(),
            'stopped' => $stream->isStopped(),
            'error' => $stream->isError(),
            'error_message' => $stream->getErrorMessage(),
            'process_pid' => $stream->getProcessPid(),
            'batches_total' => isset($state['batches_total']) ? (int) $state['batches_total'] : null,
            'batches_processed' => isset($state['batches_processed']) ? (int) $state['batches_processed'] : null,
            'state' => $state,
        ];
    }

    /**
     * @return array<string,mixed>
     */
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

    /**
     * @param array<string,mixed> $data
     */
    private function extractUid(array $data): string
    {
        $uid = trim((string) ($data['uid'] ?? ''));
        if ($uid === '') {
            $uid = trim((string) $this->option('uid'));
        }
        if ($uid === '') {
            throw new \InvalidArgumentException('Поле uid обязательно (в --data или --uid)');
        }

        return $uid;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function requireNonEmptyString(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException("Поле {$key} обязательно и не должно быть пустым");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
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
