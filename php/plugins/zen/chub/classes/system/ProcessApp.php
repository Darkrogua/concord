<?php namespace Zen\Chub\Classes\System;

use Exception;
use Zen\Chub\Models\Flow;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\Support\Strings;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\Stream;

class ProcessApp
{
    private ?Flow $flow = null;

    public function __construct(?Flow $flow = null) {
        $this->flow = $flow;
    }

    public static function make(?Flow $flow = null): self
    {
        return new self($flow);
    }

    private function isFlowFolder(): bool
    {
        return $this->flow && (bool) ($this->flow->is_folder ?? false);
    }

    private function getFlowPath(): string
    {
        if (!$this->flow) {
            throw new Exception('Flow не определён');
        }

        if ($this->isFlowFolder()) {
            throw new Exception('Запись-папка не использует flow_{code}.json');
        }

        return Files::make()->defineFilePath(
            base_path(
                'plugins/zen/chub/data/flows/flow_' . $this->flow->code . '.json'
            )
        );
    }

    public function getFlowData(): array
    {
        if ($this->isFlowFolder()) {
            return [];
        }

        return Transformers::make()->arrayFromFile($this->getFlowPath()) ?? [];
    }

    public function getFlowStates(): array
    {
        $flow_data = $this->getFlowData();
        
        $states = [];
        foreach ($flow_data as $process) {
            $stream_uid = $process['code'];
            $stream = Stream::connect($stream_uid);
            $state_data = $stream->getStateData();
            if (!$state_data) {
                $states[] = [
                    'code' => $stream_uid,
                    'in_process' => false,
                    'completed' => false,
                    'error' => null,
                    'batches_total' => null,
                    'batches_processed' => null,
                ];
            } else {
                $states[] = [
                    'code' => $stream_uid,
                    'in_process' => $stream->inProcess(),
                    'completed' => boolval($state_data['completed_at']),
                    'error' => boolval($state_data['error_at']) ? $state_data['error_message'] : null,
                    'batches_total' => $state_data['batches_total'],
                    'batches_processed' => $state_data['batches_processed'],
                ];
            }
        }
        
        return $states;
    }

    public function updateFlow(array $flow_data): void
    {
        if ($this->isFlowFolder()) {
            return;
        }

        $this->validateUniqueProcessCodes($flow_data);
        Transformers::make()->arrayToFile($flow_data, $this->getFlowPath());
    }

    /**
     * Проверяет уникальность code процесса:
     * - внутри текущего потока
     * - среди всех процессов в других потоках
     * @throws \Exception
     */
    private function validateUniqueProcessCodes(array $flow_data): void
    {
        $codes_registry = [];

        foreach ($flow_data as $index => $process) {
            $process_code = trim((string) ($process['code'] ?? ''));
            if ($process_code === '') {
                continue;
            }

            if (isset($codes_registry[$process_code])) {
                $first_index = $codes_registry[$process_code] + 1;
                $second_index = $index + 1;
                throw new \Exception(
                    "Код процесса '{$process_code}' дублируется в текущем потоке (позиции {$first_index} и {$second_index})."
                );
            }

            $codes_registry[$process_code] = $index;
        }

        $flows_dir = base_path('plugins/zen/chub/data/flows');
        if (!is_dir($flows_dir)) {
            return;
        }

        $current_flow_file = realpath($this->getFlowPath());
        $flow_files = Files::make()->filesList($flows_dir, false, [
            'allowed_extensions' => ['json'],
        ]);

        foreach ($flow_files as $file) {
            $file_name = (string) ($file['name'] ?? '');
            $file_path = (string) ($file['path'] ?? '');

            if ($file_name === 'flows_data.json') {
                continue;
            }
            if (!str_starts_with($file_name, 'flow_')) {
                continue;
            }

            $real_file_path = realpath($file_path);
            if ($current_flow_file && $real_file_path && $current_flow_file === $real_file_path) {
                continue;
            }

            $external_flow_data = Transformers::make()->arrayFromFile($file_path) ?? [];
            if (!$external_flow_data) {
                continue;
            }

            foreach ($external_flow_data as $external_process) {
                $external_code = trim((string) ($external_process['code'] ?? ''));
                if ($external_code === '') {
                    continue;
                }

                if (!isset($codes_registry[$external_code])) {
                    continue;
                }

                $external_flow_code = str_replace(['flow_', '.json'], '', $file_name);
                throw new \Exception(
                    "Код процесса '{$external_code}' уже используется в потоке '{$external_flow_code}'."
                );
            }
        }
    }

    /**
     * Переместить процесс вверх или вниз
     */
    public function moveProcess(string $process_uid, string $direction): void
    {
        if ($this->isFlowFolder()) {
            return;
        }

        $flow_data = $this->getFlowData();
        if (!$flow_data) {
            return;
        }

        $process_index = null;
        foreach ($flow_data as $index => $process) {
            if (($process['code'] ?? null) === $process_uid) {
                $process_index = $index;
                break;
            }
        }

        if ($process_index === null) {
            return;
        }

        if ($direction === 'up') {
            $swap_index = $process_index - 1;
        } elseif ($direction === 'down') {
            $swap_index = $process_index + 1;
        } else {
            return;
        }

        if (!array_key_exists($swap_index, $flow_data)) {
            return;
        }

        $current_process = $flow_data[$process_index];
        $flow_data[$process_index] = $flow_data[$swap_index];
        $flow_data[$swap_index] = $current_process;

        $this->updateFlow($flow_data);
    }

    public function createProcess(): void
    {
        if ($this->isFlowFolder()) {
            return;
        }

        $flow_data = $this->getFlowData();
        $flow_data[] = [
            'name' => 'Без имени',
            'code' => Strings::make()->createUuid(),
            'active' => true,
            'handler_path' => null,
            'handler_path_source' => null,
            'handler_path_target' => null,
            'handler_start_path' => null,
            'handler_start_path_source' => null,
            'handler_start_path_target' => null,
            'handler_finish_path' => null,
            'handler_finish_path_source' => null,
            'handler_finish_path_target' => null,
            'handler_error_path' => null,
            'handler_error_path_source' => null,
            'handler_error_path_target' => null,
            'handler_stop_path' => null,
            'handler_stop_path_source' => null,
            'handler_stop_path_target' => null,
        ];
        Transformers::make()->arrayToFile($flow_data, $this->getFlowPath());
    }

    /**
     * Убить процесс по его PID, в качестве необязательного параметра флаг $clear_state - Очистить состояние
     * @param string $process_uid - UID процесса
     * @param bool $clear_state - Флаг очистки состояния
     * @return void
     */
    public function killProcess(string $process_uid, bool $clear_state = false): void
    {
        if (!Stream::exists($process_uid)) {
            return;
        }

        $stream = Stream::connect($process_uid);

        if ($stream->inProcess()) {
            $stream->killStream();
        }

        if ($clear_state) {
            $stream->clearState();
        }
    }

    public function deleteProcess(int $process_index)
    {
        if ($this->isFlowFolder()) {
            return;
        }

        if ($process_index < 0) {
            return;
        }

        $flow_data = $this->getFlowData();
        if (!array_key_exists($process_index, $flow_data)) {
            return;
        }

        unset($flow_data[$process_index]);
        $flow_data = array_values($flow_data);

        Transformers::make()->arrayToFile($flow_data, $this->getFlowPath());
    }

    // Запустить процесс из UI
    public function runProcess(string $process_uid)
    {
        if ($this->isFlowFolder()) {
            return;
        }

        $flow_data = $this->getFlowData();
        $process_data = collect($flow_data)->firstWhere('code', $process_uid);

        if (!boolval($process_data['active'])) {
            return;
        }

        $handler_path = $process_data['handler_path'] ?? null;

        if (!$handler_path) {
            return;
        }
        
        # Если состояние потока существует
        if (Stream::exists($process_uid)) {
            # Подключаемся к потоку
            $stream = Stream::connect($process_uid);

            # В случае если была ошибка очищаем состояние
            if ($stream->isError()) {
                $stream = $stream->clearState();
                return;
            }

            # Проверяем не завершён ли процесс
            $completed = $stream->isCompleted();

            # Если завершён то очищаем состояние
            if ($completed) {
                $stream = $stream->clearState();
                return;
            }

            # Вот тут вероятность паузы или запуска
            if ($stream->inProcess()) {
                $stream->stopStream();
            } else {
                $stream->resumeStream();
            }
            
            return;
        }

        $this->runStream($process_uid, $process_data);
    }

    public function runScheduleProcess(string $process_uid)
    {
        # Если процесс уже существует, запускаем его только в случае если он
        # уже завершён ИЛИ не работает иначе чистим его состояние
        if (Stream::exists($process_uid)) {
            $stream = Stream::connect($process_uid);
            if (!$stream->isCompleted() && $stream->inProcess()) {
                return;
            }
            $stream->clearState();
        }

        $files = Files::make()->filesList(base_path('plugins/zen/chub/data/flows'));

        $process_data = null;
        foreach ($files as $file) {
            $flow = Transformers::make()->arrayFromFile($file['path']);
            if (!$flow) {
                continue;
            }

            foreach ($flow as $candidate) {
                if (($candidate['code'] ?? '') === $process_uid) {
                    $process_data = $candidate;
                    break 2;
                }
            }
        }

        if ($process_data === null) {
            $ex = new \RuntimeException("Процесс «{$process_uid}» не найден ни в одном flow-файле.");
            LogsApp::addErrorFromThrowable($ex, 'ProcessApp: процесс не найден в flow', [
                'process_uid' => $process_uid,
            ]);
            throw $ex;
        }

        $this->runStream($process_uid, $process_data);
    }

    private function runStream(string $process_uid, array $process_data): void
    {
        if (!boolval($process_data['active'])) {
            return;
        }

        if (Stream::exists($process_uid)) {
            LogsApp::addError([
                '$process_uid' => $process_uid,
                '$process_data' => $process_data
            ], 'Попытка запуска потока планировщика без очистки состояния');
            return;
        }

        $stream = Stream::create($process_uid);
        $stream->defineHandler(
            $process_data['handler_path'],
            $process_data['handler_path_source'] ?? null,
            $process_data['handler_path_target'] ?? null
        );
        if (!empty($process_data['handler_start_path'])) {
            $stream->defineStartHandler(
                $process_data['handler_start_path'],
                $process_data['handler_start_path_source'] ?? null,
                $process_data['handler_start_path_target'] ?? null
            );
        }
        if (!empty($process_data['handler_finish_path'])) {
            $stream->defineFinishHandler(
                $process_data['handler_finish_path'],
                $process_data['handler_finish_path_source'] ?? null,
                $process_data['handler_finish_path_target'] ?? null
            );
        }
        if (!empty($process_data['handler_error_path'])) {
            $stream->defineErrorHandler(
                $process_data['handler_error_path'],
                $process_data['handler_error_path_source'] ?? null,
                $process_data['handler_error_path_target'] ?? null
            );
        }
        if (!empty($process_data['handler_stop_path'])) {
            $stream->defineStopHandler(
                $process_data['handler_stop_path'],
                $process_data['handler_stop_path_source'] ?? null,
                $process_data['handler_stop_path_target'] ?? null
            );
        }

        $stream->streamRun();
    }

    /**
     * Ожидание завершения потока (completed / error / stopped) с таймаутами.
     * Паттерн согласован с parser-orchestrator (GamaParser и др.).
     *
     * @throws Exception
     */
    public function waitForStreamCompletion(string $process_uid, int $timeout_seconds = 7200): void
    {
        $process_uid = trim($process_uid);
        if ($process_uid === '') {
            throw new Exception('Не указан код потока для ожидания.');
        }

        $started_at = time();
        $fail = function (string $message, array $context = []) use ($process_uid, $started_at): void {
            $ex = new Exception($message);
            LogsApp::addErrorFromThrowable($ex, 'ProcessApp: дочерний поток не завершился', array_merge([
                'process_uid' => $process_uid,
                'wait_seconds' => time() - $started_at,
            ], $context));
            throw $ex;
        };

        while (true) {
            sleep(1);

            if (!Stream::exists($process_uid)) {
                if ((time() - $started_at) > 15) {
                    $fail('Child stream state was not created: '.$process_uid);
                }
                continue;
            }

            $stream = Stream::connect($process_uid);
            if ($stream->isCompleted() || $stream->isError() || $stream->isStopped()) {
                break;
            }

            if (!$stream->inProcess() && (time() - $started_at) > 30) {
                $fail('Child stream is not running and not completed: '.$process_uid);
            }

            if ((time() - $started_at) > $timeout_seconds) {
                $fail('Child stream timeout exceeded: '.$process_uid);
            }
        }

        $stream = Stream::connect($process_uid);
        if ($stream->isError()) {
            $fail(
                'Child stream failed: '.$process_uid.'; '.(string) $stream->getErrorMessage(),
                ['stream_error_message' => $stream->getErrorMessage()]
            );
        }
        if ($stream->isStopped()) {
            $fail('Child stream stopped before completion: '.$process_uid);
        }
    }
}