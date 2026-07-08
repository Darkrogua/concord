<?php namespace Zen\Chub\Classes\System;

use Exception;
use Illuminate\Support\Str;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Models\Settings;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Classes\System\CommandApp;

/**
 * Stream — это специализированный оркестратор для долгих пакетных задач
 * с жёстким контролем и минимальными зависимостями, разработан для
 * фоновой пакетной обработки с сохранением состояния.
 * @author zenc0dr (2026)
 */

class Stream
{
    private const int HEARTBEAT_TTL_SECONDS_DEFAULT = 1800;

    private string $stream_uid;
    private string $states_dir;
    private string $output = '/dev/null';
    private string $output_errors = '/dev/null';
    private bool $handling_error = false; # Защита от рекурсивного захвата исключений
    private ?array $state_cache = null; # Кеширование состояния сессии для уменьшения чтения файла сессии

    private function __construct(string $uid)
    {
        $this->stream_uid = $uid;
        $this->defineStorage();
    }

    /**
     * Создать новый поток
     * @param string|null $uid
     * @return self
     */
    public static function create(?string $uid = null): self
    {
        $uid = $uid ?: (string) Str::uuid();
        $stream = new self($uid);

        if (self::exists($uid)) {
            LogsApp::addInfo(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'Отслеживание состояния');
            throw new Exception('Состояние уже создано');
        }

        ## тут логика создания состояния ##
        $stream->createState();

        return $stream;
    }

    /**
     * Подключиться к существующему потоку
     * @param string $uid
     * @return self
     */
    public static function connect(string $uid): self
    {
        $stream = new self($uid);
        return $stream;
    }

    /**
     * Проверить, существует ли поток
     * @param string $uid
     * @return bool
     */
    public static function exists(string $uid): bool
    {
        $stream = new self($uid);

        return file_exists($stream->getStatePath());
    }

    /**
     * Очистить состояние потока
     */
    public static function clear(string $uid): void
    {
        if (self::exists($uid)) {
            self::connect($uid)->clearState();
        }
    }

    /**
     * Очистить хранилище всёх состояний
     */
    public static function clearStateStorage(): void
    {
        $states_dir = self::getStatesDirPath();
        $safe_dir = escapeshellarg($states_dir);
        $cmd = "rm -f {$safe_dir}/* {$safe_dir}/.[!.]* {$safe_dir}/..?*";
        shell_exec($cmd);
    }

    /**
     * Получить UID потока
     * @return string
     */
    public function uid(): string
    {
        return $this->stream_uid;
    }

    /**
     * Остановить поток с пакетной обработкой
     */
    public function stopStream()
    {
        $this->setStateValue('command_stop', true);
    }

    /**
     * Убить процесс по его PID
     */
    public function killStream(): bool
    {
        $pid = $this->getProcessPid();
        if ($pid <= 0) {
            return false;
        }

        # Убеждаемся, что pid принадлежит нашему потоку
        if (!$this->inProcess()) {
            return false;
        }

        $proc_dir = "/proc/{$pid}";
        if (function_exists('posix_kill')) {
            $sigterm = \defined('SIGTERM') ? \constant('SIGTERM') : 15;
            $sigkill = \defined('SIGKILL') ? \constant('SIGKILL') : 9;
            @posix_kill($pid, $sigterm);
            usleep(200000);
            if (is_dir($proc_dir)) {
                @posix_kill($pid, $sigkill);
            }
        } else {
            shell_exec('kill -TERM ' . intval($pid));
            usleep(200000);
            if (is_dir($proc_dir)) {
                shell_exec('kill -KILL ' . intval($pid));
            }
        }

        $killed = !is_dir($proc_dir);
        if ($killed) {
            $this->setStateValues([
                'process_pid' => null,
                'process_started_at' => null,
                'process_started_ticks' => null,
                'command_stop' => true,
                'stopped_at' => now()->toDateTimeString(),
            ]);
        }

        return $killed;
    }

    /**
     * Возобновить поток с пакетной обработкой
     */
    public function resumeStream()
    {
        $this->setStateValue('command_stop', false);
        $this->setStateValue('stopped_at', null);
        $this->setStateValue('resume_mode', true);
        $this->streamRun(true);
    }

    /**
     * Определить обработчик для потока
     * @param string $handler_path
     */
    public function defineHandler(
        string $handler_path, # Адрес метода процесса (dotpath)
        ?string $data_source = null, # Источник пакетов в виде массива (dotpath)
        ?string $data_target = null  # Вывод данных в результате процесса (dotpath)
    ): void
    {
        $this->defineHook('handler_path', $handler_path, $data_source, $data_target);
    }

    /**
     * Определить обработчик для старта процесса
     * @param string $handler_path
     */
    public function defineStartHandler(
        string $handler_path, # Адрес метода процесса (dotpath)
        ?string $data_source = null, # Источник пакетов в виде массива (dotpath)
        ?string $data_target = null  # Вывод данных в результате процесса (dotpath)
    ): void
    {
        $this->defineHook('handler_start_path', $handler_path, $data_source, $data_target);
    }

    /**
     * Определить обработчик для окончания процесса
     * @param string $handler_path
     */
    public function defineFinishHandler(
        string $handler_path, # Адрес метода процесса (dotpath)
        ?string $data_source = null, # Источник пакетов в виде массива (dotpath)
        ?string $data_target = null  # Вывод данных в результате процесса (dotpath)
    ): void
    {
        $this->defineHook('handler_finish_path', $handler_path, $data_source, $data_target);
    }

    /**
     * Определить обработчик для ошибок
     * @param string $handler_path
     */
    public function defineErrorHandler(
        string $handler_path, # Адрес метода процесса (dotpath)
        ?string $data_source = null, # Источник пакетов в виде массива (dotpath)
        ?string $data_target = null  # Вывод данных в результате процесса (dotpath)
    ): void
    {
        $this->defineHook('handler_error_path', $handler_path, $data_source, $data_target);
    }

    /**
     * Определить обработчик для остановки пакетной обработки
     * @param string $handler_path
     */
    public function defineStopHandler(
        string $handler_path, # Адрес метода процесса (dotpath)
        ?string $data_source = null, # Источник пакетов в виде массива (dotpath)
        ?string $data_target = null  # Вывод данных в результате процесса (dotpath)
    ): void
    {
        $this->defineHook('handler_stop_path', $handler_path, $data_source, $data_target);
    }
    /**
     * Определить хук обработки
     */
    private function defineHook(
        string $base_key,
        string $handler_path,
        ?string $data_source = null,
        ?string $data_target = null
    ): void
    {
        $this->setStateValues([
            $base_key => $handler_path,
            $base_key . '_source' => $data_source,
            $base_key . '_target' => $data_target,
        ]);
    }
    /**
     * Добавить новый пакет в поток
     * @param array $data
     */
    public function addBatch(array $data): void
    {
        $batch_number = $this->getStateData('batches_total');
        $batch_patch = $this->getBatchPath($batch_number);
        Transformers::make()->arrayToFile(
            $data,
            $batch_patch
        );
        $batch_number++;
        $this->setStateValue('batches_total', $batch_number);
    }

    /**
     * Запустить процесс в фоне
     */
    public function streamRun(bool $resume = false): void
    {
        $state_data = $this->getStateData();

        # Если это не возобновление процесса и был назначен PID - Ничего не делать
        if (!$resume && $state_data['process_pid']) {
            return;
        }

        # Проверка на наличие обработчика
        $handler_path = $state_data['handler_path'];
        if (!$handler_path) {
            throw new Exception('Не указан обработчик');
        }

        $php_path = $this->resolvePhpPath();
        $artisan_path = base_path() . '/artisan';

        if (!file_exists($artisan_path)) {
            $this->logStartError('artisan_not_found', [
                'artisan_path' => $artisan_path,
                'uid' => $this->stream_uid,
            ]);
            throw new Exception("Файл artisan не найден: {$artisan_path}");
        }

        $pid = $this->startBackgroundProcess($php_path, $artisan_path, $this->stream_uid);
        if (!$pid) {
            throw new Exception("Не удалось запустить поток {$this->stream_uid} в фоне");
        }

        $this->setStateValues([
            'process_pid' => $pid,
            'process_started_at' => now()->toDateTimeString(),
            'process_started_ticks' => $this->getProcessStartTicks($pid),
        ]);
    }

    private function resolvePhpPath(): string
    {
        $php_path = trim((string) Settings::get('php_path'));
        if (!$php_path) {
            $php_path = trim((string) env('PHP_PATH'));
        }
        if (!$php_path) {
            $php_path = '/usr/local/bin/php';
        }

        # Если указан абсолютный путь - проверим, что бинарник существует и исполняемый
        if (str_contains($php_path, '/')) {
            if (!file_exists($php_path) || !is_executable($php_path)) {
                $this->logStartError('php_path_invalid', [
                    'php_path' => $php_path,
                    'uid' => $this->stream_uid,
                ]);
                throw new Exception("Некорректный путь к php бинарнику: {$php_path}");
            }
        }

        return $php_path;
    }

    private function startBackgroundProcess(string $php_path, string $artisan_path, string $uid): ?int
    {
        $pid = $this->startWithProcOpen($php_path, $artisan_path, $uid, true, 'proc_open_setsid');
        if ($pid) {
            return $pid;
        }

        $pid = $this->startWithProcOpen($php_path, $artisan_path, $uid, false, 'proc_open_plain');
        if ($pid) {
            return $pid;
        }

        $pid = $this->startWithShellExec($php_path, $artisan_path, $uid, true, 'shell_exec_setsid');
        if ($pid) {
            return $pid;
        }

        return $this->startWithShellExec($php_path, $artisan_path, $uid, false, 'shell_exec_plain');
    }

    private function startWithProcOpen(
        string $php_path,
        string $artisan_path,
        string $uid,
        bool $with_setsid,
        string $method
    ): ?int {
        if (!function_exists('proc_open')) {
            $this->logStartError('proc_open_unavailable', [
                'uid' => $uid,
                'method' => $method,
            ]);
            return null;
        }

        $cmd = $this->buildBackgroundCommand($php_path, $artisan_path, $uid, $with_setsid);
        $descriptor_spec = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = @proc_open($cmd, $descriptor_spec, $pipes);
        if (!is_resource($process)) {
            $this->logStartError('proc_open_failed', [
                'uid' => $uid,
                'method' => $method,
                'cmd' => $cmd,
                'last_error' => error_get_last(),
            ]);
            return null;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        $pid = trim((string) $stdout);
        if (ctype_digit($pid)) {
            return (int) $pid;
        }

        $this->logStartError('pid_not_received', [
            'uid' => $uid,
            'method' => $method,
            'cmd' => $cmd,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exit_code,
        ]);

        return null;
    }

    private function startWithShellExec(
        string $php_path,
        string $artisan_path,
        string $uid,
        bool $with_setsid,
        string $method
    ): ?int {
        if (!function_exists('shell_exec')) {
            $this->logStartError('shell_exec_unavailable', [
                'uid' => $uid,
                'method' => $method,
            ]);
            return null;
        }

        $cmd = $this->buildBackgroundCommand($php_path, $artisan_path, $uid, $with_setsid);
        $result = @shell_exec($cmd);
        $pid = trim((string) $result);

        if (ctype_digit($pid)) {
            return (int) $pid;
        }

        $this->logStartError('shell_exec_failed', [
            'uid' => $uid,
            'method' => $method,
            'cmd' => $cmd,
            'result' => $result,
            'last_error' => error_get_last(),
        ]);

        return null;
    }

    private function buildBackgroundCommand(
        string $php_path,
        string $artisan_path,
        string $uid,
        bool $with_setsid
    ): string {
        $prefix = $with_setsid ? 'setsid ' : '';
        $php_path = escapeshellarg($php_path);
        $artisan_path = escapeshellarg($artisan_path);
        $uid = escapeshellarg($uid);
        $output = escapeshellarg($this->output);
        $output_errors = escapeshellarg($this->output_errors);

        return "{$prefix}{$php_path} {$artisan_path} chub:stream process --uid={$uid} "
            . "</dev/null >>{$output} 2>>{$output_errors} & echo $!";
    }

    private function logStartError(string $error_code, array $context = []): void
    {
        LogsApp::addError(
            array_merge([
                'error_code' => $error_code,
                'stream_uid' => $this->stream_uid,
                'php_path_setting' => Settings::get('php_path'),
                'php_path_env' => env('PHP_PATH'),
            ], $context),
            'Ошибка запуска фонового процесса потока'
        );
    }

    /**
     * Проверить, жив ли поток
     * @return bool
     */
    public function inProcess(): bool
    {
        $state_data = $this->getStateDataFresh();
        $process_pid = $state_data['process_pid'] ?? null;

        if (!is_numeric($process_pid)) {
            return false;
        }

        $pid = (int) $process_pid;
        if ($pid <= 0) {
            return false;
        }

        $proc_dir = "/proc/{$pid}";
        if (!is_dir($proc_dir)) {
            $this->clearRunningProcessState('process_dir_missing');
            return false;
        }

        $cmdline_path = "{$proc_dir}/cmdline";
        if (is_readable($cmdline_path)) {
            $cmdline = str_replace("\0", ' ', (string) file_get_contents($cmdline_path));
            $uid = $this->stream_uid;

            if (!str_contains($cmdline, 'artisan')
                || !str_contains($cmdline, 'chub:stream')
                || !str_contains($cmdline, "--uid={$uid}")
            ) {
                $this->clearRunningProcessState('pid_reused_or_not_our_process');
                return false;
            }
        }

        $stored_ticks = $state_data['process_started_ticks'] ?? null;
        $actual_ticks = $this->getProcessStartTicks($pid);
        if ($stored_ticks && $actual_ticks && (string) $stored_ticks !== (string) $actual_ticks) {
            $this->clearRunningProcessState('pid_start_ticks_mismatch');
            return false;
        }

        if ($this->isHeartbeatExpired($state_data['heartbeat_at'] ?? null, $state_data)) {
            $this->clearRunningProcessState('heartbeat_ttl_expired');
            return false;
        }

        return true;
    }

    /**
     * Инициализировать процесс
     */
    public function streamProcessInit(): void
    {
        $current_pid = getmypid();
        $stored_pid = intval($this->getStateData('process_pid'));

        if ($current_pid !== $stored_pid) {
            return;
        }

        $this->streamProcess();
    }

    /**
     * Получить данные состояния
     * @param string|null $key
     * @return mixed
     */
    public function getStateData(?string $key = null): mixed
    {
        if ($this->state_cache === null) {
            $this->state_cache = Transformers::make()->arrayFromFile(
                $this->getStatePath()
            );
        }

        $state_data = $this->state_cache;

        return $key ? ($state_data[$key] ?? null) : $state_data;
    }

    /**
     * Получить актуальные данные состояния (без кеша)
     * @param string|null $key
     * @return mixed
     */
    public function getStateDataFresh(?string $key = null): mixed
    {
        $this->state_cache = Transformers::make()->arrayFromFile(
            $this->getStatePath()
        );

        return $key ? ($this->state_cache[$key] ?? null) : $this->state_cache;
    }

    /**
     * Получить PID процесса из состояния
     */
    public function getProcessPid(): int
    {
        return intval($this->getStateData('process_pid'));
    }

    /**
     * Преобразовать dotpath в [class, method]
     */
    private function dotpathToHandler(string $dotpath): array
    {
        $method = \Str::afterLast($dotpath, '.');
        $handler = str_replace(".$method", '', $dotpath);
        $handler = str_replace(".", '\\', $handler);

        return [
            'class' => $handler,
            'method' => $method
        ];
    }

    /**
     * Запустить процесс
     */
    private function streamProcess(): void
    {
        # Массив состояния потока
        $state_data = $this->getStateData();
        $resume_mode = boolval($state_data['resume_mode'] ?? false);
        if ($resume_mode) {
            $this->setStateValue('resume_mode', false);
        }

        # Если установлен хук старта потока
        if (!$resume_mode && $state_data['handler_start_path']) {
            $this->runHandler('handler_start_path');
        }

        # Получить/добавить пакеты из источника если таковой указан
        if (!$resume_mode && $state_data['handler_path_source']) {
            $this->getBatchesFromSource($state_data['handler_path_source']);
            # Перечитываем состояние на случай если пакеты были добавлены
            $state_data = $this->getStateDataFresh();
        }

        # Перечитываем состояние перед расчетом пакетов
        $state_data = $this->getStateDataFresh();
        # Окончательное количество пакетов потока
        $batches_total = (int) ($state_data['batches_total'] ?? 0);

        # Если пакетов не существует то выполняем метод только один раз
        if ($batches_total === 0) {
            $handler_data = $this->methodExec($state_data['handler_path']);

            # Отправляем данные с обработки пакета по указанному dotpath
            if ($handler_data && $state_data['handler_path_target']) {
                $this->methodExec($state_data['handler_path_target'], $handler_data);
            }

            # Вписываем время завершения и выполняем финишных хук
            $this->setStateValue('completed_at', now()->toDateTimeString());
            $this->finishHook($state_data);
            return;
        }

        # Пакетная обработка
        while (true) {
            # Повторно читаем стейт при обработке каждого батча
            $state_data = $this->getStateDataFresh();

            # Мягкая остановка потока с пакетной обработкой
            # финишный хук не запускается
            if ($state_data['command_stop']) {
                if ($state_data['handler_stop_path']) {
                    $this->runHandler('handler_stop_path');
                }
                $this->setStateValue('stopped_at', now()->toDateTimeString());
                return;
            }

            $batches_total = (int) ($state_data['batches_total'] ?? 0);
            $batches_processed = (int) ($state_data['batches_processed'] ?? 0);

            if ($batches_processed >= $batches_total) {
                $this->setStateValue('completed_at', now()->toDateTimeString());
                $this->finishHook($state_data);
                return;
            }

            $batch = $this->getBatch();

            # Выполнение процесса c пакетом из очереди
            $handler_data = $this->methodExec($state_data['handler_path'], $batch);

            # Отправляем данные с обработки пакета по указанному dotpath
            if ($handler_data && $state_data['handler_path_target']) {
                $this->methodExec($state_data['handler_path_target'], $handler_data);
            }

            # Записываем прогресс
            $this->setStateValues([
                'batches_processed' => $batches_processed + 1,
            ]);
        }
    }

    /**
     * Выполнение завершающего хука, если он установлен
     */
    private function finishHook(array $state_data): void
    {
        if ($state_data['handler_finish_path']) {
            $this->runHandler('handler_finish_path');
        }
    }

    /**
     * Выполнить источник
     */
    public function runHandler(string $handler_state_key, mixed $source_data = null): mixed
    {
        $state_data = $this->getStateData();

        $handler_name = $state_data[$handler_state_key]; # Имя обработчика

        # Если обработчика нет то ничего не делаем
        if (!$handler_name) {
            return null;
        }

        $handler_source = $state_data[$handler_state_key . '_source']; # Имя метода источника
        $handler_target = $state_data[$handler_state_key . '_target']; # Имя метода цели

        # Если указан источник данных
        if ($handler_source) {
            if ($source_data === null) {
                $source_data = $this->methodExec($handler_source);
            } else {
                $source_data = $this->methodExec($handler_source, $source_data);
            }
        }

        # Выполнение обработчика
        $handler_data = $this->methodExec($handler_name, $source_data);

        # Если указан метод цели
        if ($handler_target) {
            $this->methodExec($handler_target, $handler_data);
        }

        return $handler_data;
    }

    /**
     * Выполнение процесса по dotpath
     */
    public function methodExec(string $dotpath, mixed $data = null): mixed
    {
        try {
            if (str_starts_with($dotpath, 'run:')) {
                $command_code = trim(substr($dotpath, 4));
                if ($command_code === '') {
                    return null;
                }

                $input_data = is_array($data) ? $data : null;
                return CommandApp::exec($command_code, $input_data);
            }

            $handler = $this->dotpathToHandler($dotpath);
            if ($data === null) {
                return app($handler['class'])->{$handler['method']}();
            }
            return app($handler['class'])->{$handler['method']}($data);
        } catch (\Throwable $e) {

            LogsApp::addErrorFromThrowable($e, 'Stream: ошибка handler', [
                'stream_uid' => $this->stream_uid,
                'handler_dotpath' => $dotpath,
            ]);

            $error_message = $e->getMessage();
            $error_line = $e->getLine();
            $error_file = $e->getFile();

            $this->setStateValues([
                'error_at' => now()->toDateTimeString(),
                'error_message' => "$error_file:$error_line - $error_message"
            ]);

            if (!$this->handling_error) {
                $this->handling_error = true;
                try {
                    $this->runHandler('handler_error_path', ['error' => $e]);
                } finally {
                    $this->handling_error = false;
                }
            }
            throw $e;
        }
    }

    /**
     * Получить массив пакетов из метода по dotpath
     * и добавить в пакеты потока
     */
    private function getBatchesFromSource(string $dotpath): void
    {
        $batches = $this->methodExec($dotpath);
        if ($batches && is_array($batches)) {
            foreach ($batches as $batch) {
                $this->addBatch($this->normalizeBatchPayload($batch));
            }
        }
    }

    /**
     * Получить пакет
     * @return mixed
     */
    private function getBatch(): mixed
    {
        $batch_number = $this->getStateData('batches_processed');
        $batch_patch = $this->getBatchPath($batch_number);
        $batch_data = Transformers::make()->arrayFromFile($batch_patch);
        return $this->restoreBatchPayload($batch_data);
    }

    /**
     * Нормализовать пакет для файлового хранения.
     * Поток исторически хранит пакеты только в виде массива,
     * поэтому скаляры упаковываются в служебную структуру.
     */
    private function normalizeBatchPayload(mixed $batch): array
    {
        if (is_array($batch)) {
            return $batch;
        }

        return [
            '__stream_batch_type' => 'scalar',
            '__stream_batch_value' => $batch,
        ];
    }

    /**
     * Восстановить исходное значение пакета после чтения с диска.
     */
    private function restoreBatchPayload(mixed $batch): mixed
    {
        if (!is_array($batch)) {
            return $batch;
        }

        if (($batch['__stream_batch_type'] ?? null) !== 'scalar') {
            return $batch;
        }

        return $batch['__stream_batch_value'] ?? null;
    }

    /**
     * Определить хранилище
     */
    private function defineStorage(): void
    {
        $this->states_dir = self::getStatesDirPath();
    }

    /**
     * Получить путь до хранилища состояний
     * @return string
     */
    private static function getStatesDirPath(): string
    {
        return base_path('storage/chub/states');
    }

    /**
     * Получить путь до состояния
     * @return string
     */
    private function getStatePath(): string
    {
        return Files::make()
            ->defineFilePath(
                $this->states_dir . '/' . $this->stream_uid . '.json'
            );
    }

    /**
     * Получить путь до пакета
     * @param int $batch_number
     * @return string
     */
    private function getBatchPath(int $batch_number): string
    {
        return Files::make()
            ->defineFilePath(
                join('/', [
                    $this->states_dir,
                    $this->stream_uid . ".$batch_number.json"
                ])
            );
    }

    /**
     * Создать состояние
     * тут должны быть определены все возможные ключи состояния
     * и их значения по умолчанию
     */
    private function createState(): void
    {
        $now_sting = now()->toDateTimeString();
        $this->setStateData([
            'handler_path' => null, # Путь до метода в формате dotpath ex: 'Zen.Chub.Classes.Tests.HandlersTests.testHandler'
            'handler_path_source' => null, # Источник пакетов в виде массива массивов или массива скаляров (dotpath)
            'handler_path_target' => null, # Вывод данных в результате процесса (dotpath)
            'handler_start_path' => null, # Путь до обработчика хука до старта процесса
            'handler_start_path_source' => null, # Источник данных обработчика (dotpath)
            'handler_start_path_target' => null, # Вывод данных в результате процесса (dotpath)
            'handler_finish_path' => null, # Путь до обработчика хука после старта процесса
            'handler_finish_path_source' => null, # Источник данных обработчика (dotpath)
            'handler_finish_path_target' => null, # Вывод данных в результате процесса (dotpath)
            'handler_error_path' => null, # Путь до обработчика ошибок
            'handler_error_path_source' => null, # Источник данных обработчика (dotpath)
            'handler_error_path_target' => null, # Вывод данных в результате процесса (dotpath)
            'handler_stop_path' => null, # Путь до обработчика при остановке пакетной обработки
            'handler_stop_path_source' => null, # Источник данных обработчика (dotpath)
            'handler_stop_path_target' => null, # Вывод данных в результате процесса (dotpath)
            'command_stop' => false, # Команда стоп для прерывания пакетной обработки
            'resume_mode' => false, # Возобновление ранее остановленного процесса
            'batches_total' => 0, # Общее количество пакетов
            'batches_processed' => 0, # Количество обработанных пакетов
            'process_pid' => null, # pid - процесса
            'process_started_at' => null, # Когда стартовал процесс
            'process_started_ticks' => null, # starttime из /proc/<pid>/stat для защиты от PID reuse
            'created_at' => $now_sting,
            'heartbeat_at' => $now_sting, # Последняя активность
            'completed_at' => null, # Когда поток был завершён
            'stopped_at' => null, # Когда поток был остановлен
            'error_at' => null, # Когда произошла ошибка
            'error_message' => null, # Сообщение ошибки
        ]);
    }

    /**
     * Проверить, завершен ли поток
     * @return bool
     */
    public function isCompleted(): bool
    {
        return boolval($this->getStateData('completed_at'));
    }

    /**
     * Проверить, остановлен ли поток
     * @return bool
     */
    public function isStopped(): bool
    {
        return boolval($this->getStateData('stopped_at'));
    }

    /**
     * Произошла ли ошибка
     * @return bool
     */
    public function isError(): bool
    {
        return boolval($this->getStateData('error_at'));
    }

    /**
     * Получить сообщение ошибки
     * @return string|null
     */
    public function getErrorMessage(): string | null
    {
        return $this->getStateData('error_message');
    }

    /**
     *  Очистить все файлы состояния
     */
    public function clearState(): void
    {
        $states_dir = self::getStatesDirPath();
        $pattern = $states_dir . '/' . $this->stream_uid . '*';
        $files = glob($pattern);

        if (!$files) {
            return;
        }

        foreach ($files as $file_path) {
            if (is_file($file_path)) {
                @unlink($file_path);
            }
        }
    }

    /**
     * Пересоздать состояние
     * @return self
     */
    public function recreate(): self
    {
        $this->clearState();
        $this->createState();
        return $this;
    }

    /**
     * Установить данные состояния
     * @param array $state_data
     */
    private function setStateData(array $state_data): void
    {
        $state_data['heartbeat_at'] = now()->toDateTimeString();
        Transformers::make()->arrayToFile(
            $state_data,
            $this->getStatePath()
        );
        $this->state_cache = $state_data;
    }

    /**
     * Установить значение состояния
     * @param string $key
     * @param mixed $value
     */
    private function setStateValue(string $key, mixed $value): void
    {
        $state_data = $this->getStateDataFresh();
        $state_data[$key] = $value;
        $this->setStateData($state_data);
    }

    /**
     * Установить значения состояния (массово за раз)
     * @param array $values_bastch
     */
    public function setStateValues(array $values_bastch): void
    {
        $state_data = $this->getStateDataFresh();
        $state_data = array_merge($state_data, $values_bastch);
        $this->setStateData($state_data);
    }

    private function clearRunningProcessState(string $reason): void
    {
        $state_data = $this->getStateDataFresh();
        if (empty($state_data['process_pid'])) {
            return;
        }

        if ($reason === 'heartbeat_ttl_expired') {
            $this->terminateProcessByPid((int) $state_data['process_pid']);
        }

        LogsApp::addInfo([
            'uid' => $this->stream_uid,
            'reason' => $reason,
            'process_pid' => $state_data['process_pid'] ?? null,
            'process_started_ticks' => $state_data['process_started_ticks'] ?? null,
            'heartbeat_at' => $state_data['heartbeat_at'] ?? null,
        ], 'Сброс состояния зависшего/невалидного процесса');

        $this->setStateValues([
            'process_pid' => null,
            'process_started_at' => null,
            'process_started_ticks' => null,
        ]);
    }

    private function getProcessStartTicks(int $pid): ?string
    {
        $stat_path = "/proc/{$pid}/stat";
        if (!is_readable($stat_path)) {
            return null;
        }

        $stat = (string) @file_get_contents($stat_path);
        if (!$stat) {
            return null;
        }

        $close_pos = strrpos($stat, ')');
        if ($close_pos === false) {
            return null;
        }

        $tail = trim(substr($stat, $close_pos + 1));
        $parts = preg_split('/\s+/', $tail);
        if (!is_array($parts) || !isset($parts[19])) {
            return null;
        }

        return (string) $parts[19];
    }

    private function isHeartbeatExpired(?string $heartbeat_at, array $state_data): bool
    {
        if (!$heartbeat_at) {
            return false;
        }

        if (!empty($state_data['completed_at']) || !empty($state_data['stopped_at']) || !empty($state_data['error_at'])) {
            return false;
        }

        $heartbeat_ts = strtotime($heartbeat_at);
        if ($heartbeat_ts === false) {
            return false;
        }

        $ttl_seconds = intval(env('CHUB_STREAM_HEARTBEAT_TTL', self::HEARTBEAT_TTL_SECONDS_DEFAULT));
        if ($ttl_seconds < 60) {
            $ttl_seconds = 60;
        }

        return (time() - $heartbeat_ts) > $ttl_seconds;
    }

    private function terminateProcessByPid(int $pid): void
    {
        if ($pid <= 0) {
            return;
        }

        $proc_dir = "/proc/{$pid}";
        if (!is_dir($proc_dir)) {
            return;
        }

        if (function_exists('posix_kill')) {
            $sigterm = \defined('SIGTERM') ? \constant('SIGTERM') : 15;
            $sigkill = \defined('SIGKILL') ? \constant('SIGKILL') : 9;
            @posix_kill($pid, $sigterm);
            usleep(200000);
            if (is_dir($proc_dir)) {
                @posix_kill($pid, $sigkill);
            }
            return;
        }

        shell_exec('kill -TERM ' . intval($pid));
        usleep(200000);
        if (is_dir($proc_dir)) {
            shell_exec('kill -KILL ' . intval($pid));
        }
    }
}