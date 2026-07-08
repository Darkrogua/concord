<?php namespace Zen\Act\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Zen\Act\Classes\Support\Transformers;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Classes\System\ActApp;
use Zen\Act\Classes\System\BlockApp;

class ActCommand extends Command
{
    protected $signature = 'act:acts
        {mode : Режим работы, поддерживается только ai}
        {action? : AI-подкоманда}
        {--data= : JSON-объект входных данных}
        {--data-file= : Абсолютный путь до JSON-файла с входными данными}';

    protected $description = 'AI-управление актами (Act / ActApp)';

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
                'Поддерживается только режим ai. Используйте: ./bin/artisan act:acts ai <action>'
            );
        }

        if ($action === '') {
            return $this->emitError(
                'unknown',
                'UNKNOWN_ACTION',
                'Не указана AI-подкоманда. Начните с: ./bin/artisan act:acts ai schema'
            );
        }

        $app = ActApp::make();

        try {
            return match ($action) {
                'schema' => $this->emitSuccess('schema', $app->aiSchema()),
                'list' => $this->handleAiList($app),
                'show' => $this->handleAiShow($app),
                'create' => $this->handleAiCreate($app),
                'update' => $this->handleAiUpdate($app),
                'delete' => $this->handleAiDelete($app),
                'block-list' => $this->handleAiBlockList(),
                'block-show' => $this->handleAiBlockShow(),
                'block-create' => $this->handleAiBlockCreate(),
                'block-update' => $this->handleAiBlockUpdate(),
                'block-delete' => $this->handleAiBlockDelete(),
                'block-reorder' => $this->handleAiBlockReorder(),
                'block-verify' => $this->handleAiBlockVerify(),
                'access-get' => $this->handleAiAccessGet(),
                'access-set' => $this->handleAiAccessSet(),
                'access-migrate' => $this->handleAiAccessMigrate(),
                'list-states' => $this->handleAiListStates($app),
                'restore' => $this->handleAiRestore($app),
                'merge-states' => $this->handleAiMergeStates($app),
                'validate' => $this->handleAiValidate($app),
                default => $this->emitError(
                    $action,
                    'UNKNOWN_ACTION',
                    "Неизвестная AI-подкоманда \"{$action}\""
                ),
            };
        } catch (RuntimeException $exception) {
            return $this->emitError($action, 'RUNTIME_ERROR', $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->emitError($action, 'RUNTIME_ERROR', $exception->getMessage());
        }
    }

    private function handleAiList(ActApp $app): int
    {
        $filters = $this->readInputData();
        $items = $app->listForAi($filters);

        return $this->emitSuccess('list', [
            'items' => $items,
            'count' => count($items),
            'filters' => $filters,
        ]);
    }

    private function handleAiShow(ActApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('show', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $row = $app->showFull($id);
        if ($row === null) {
            return $this->emitError('show', 'NOT_FOUND', "Акт id={$id} не найден");
        }

        return $this->emitSuccess('show', $row);
    }

    private function handleAiCreate(ActApp $app): int
    {
        $data = $this->readInputData();
        $act = $app->createFromAiData($data);

        return $this->emitSuccess('create', [
            'act' => $app->showFull((string) $act->id),
        ]);
    }

    private function handleAiUpdate(ActApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('update', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $act = $app->updateFromAiData($id, $data);
        if ($act === null) {
            return $this->emitError('update', 'NOT_FOUND', "Акт id={$id} не найден");
        }

        return $this->emitSuccess('update', [
            'act' => $app->showFull($id),
        ]);
    }

    private function handleAiDelete(ActApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('delete', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $result = $app->deleteAct($id);

        return $this->emitSuccess('delete', $result);
    }

    private function handleAiBlockList(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $blocks = BlockApp::make()->list($act_id);

        return $this->emitSuccess('block-list', [
            'act_id' => $act_id,
            'blocks' => $blocks,
            'count' => count($blocks),
        ]);
    }

    private function handleAiBlockShow(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $id = $this->requiredString($data, 'id');
        $block = BlockApp::make()->show($act_id, $id);
        if ($block === null) {
            return $this->emitError('block-show', 'NOT_FOUND', "Блок id={$id} не найден");
        }

        return $this->emitSuccess('block-show', [
            'act_id' => $act_id,
            'block' => $block,
        ]);
    }

    private function handleAiBlockCreate(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $block = BlockApp::make()->create($act_id, $data);

        return $this->emitSuccess('block-create', [
            'act_id' => $act_id,
            'block' => $block,
            'verify' => BlockApp::make()->verify($act_id),
        ]);
    }

    private function handleAiBlockUpdate(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $id = $this->requiredString($data, 'id');
        $block = BlockApp::make()->update($act_id, $id, $data);
        if ($block === null) {
            return $this->emitError('block-update', 'NOT_FOUND', "Блок id={$id} не найден");
        }

        return $this->emitSuccess('block-update', [
            'act_id' => $act_id,
            'block' => $block,
            'verify' => BlockApp::make()->verify($act_id),
        ]);
    }

    private function handleAiBlockDelete(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $id = $this->requiredString($data, 'id');

        return $this->emitSuccess('block-delete', [
            'act_id' => $act_id,
            'result' => BlockApp::make()->delete($act_id, $id),
            'verify' => BlockApp::make()->verify($act_id),
        ]);
    }

    private function handleAiBlockReorder(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $block_ids = $data['block_ids'] ?? null;
        if (! is_array($block_ids)) {
            return $this->emitError('block-reorder', 'VALIDATION_ERROR', 'Поле block_ids обязательно и должно быть массивом');
        }

        $result = BlockApp::make()->reorder($act_id, array_values($block_ids));

        return $this->emitSuccess('block-reorder', [
            'act_id' => $act_id,
            'blocks' => $result['blocks'],
            'order' => $result['order'],
            'count' => count($result['blocks']),
            'verify' => BlockApp::make()->verify($act_id),
        ]);
    }

    private function handleAiBlockVerify(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');

        return $this->emitSuccess('block-verify', BlockApp::make()->verify($act_id));
    }

    private function handleAiAccessGet(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $resource_type = trim((string) ($data['resource_type'] ?? 'act'));
        $resource_id = trim((string) ($data['resource_id'] ?? $act_id));
        if ($resource_type === 'act') {
            $resource_id = $act_id;
        }

        $access = AccessApp::make();

        return $this->emitSuccess('access-get', [
            'meta' => $access->getMeta($act_id),
            'grants' => $access->listGrants($act_id, $resource_type, $resource_id),
            'resource_type' => $resource_type,
            'resource_id' => $resource_id,
        ]);
    }

    private function handleAiAccessSet(): int
    {
        $data = $this->readInputData();
        $act_id = $this->requiredString($data, 'act_id');
        $resource_type = $this->requiredString($data, 'resource_type');
        $resource_id = $this->requiredString($data, 'resource_id');
        $by_login = trim((string) ($data['by_login'] ?? ''));
        if ($by_login === '') {
            return $this->emitError('access-set', 'VALIDATION_ERROR', 'Поле by_login обязательно');
        }
        $grants = $data['grants'] ?? null;
        if (! is_array($grants)) {
            return $this->emitError('access-set', 'VALIDATION_ERROR', 'Поле grants обязательно');
        }

        if ($resource_type === 'act') {
            $resource_id = $act_id;
        }

        AccessApp::make()->setGrants($act_id, $resource_type, $resource_id, array_values($grants), $by_login);

        return $this->emitSuccess('access-set', [
            'grants' => AccessApp::make()->listGrants($act_id, $resource_type, $resource_id),
        ]);
    }

    private function handleAiAccessMigrate(): int
    {
        $data = $this->readInputData();
        $act_id = trim((string) ($data['act_id'] ?? ''));
        if ($act_id === '') {
            return $this->emitError('access-migrate', 'VALIDATION_ERROR', 'Поле act_id обязательно');
        }

        $count = AccessApp::make()->migrateLegacyVisibility($act_id);

        return $this->emitSuccess('access-migrate', [
            'act_id' => $act_id,
            'changes' => $count,
        ]);
    }

    private function handleAiListStates(ActApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('list-states', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $snapshots = $app->listStates($id);

        return $this->emitSuccess('list-states', [
            'id' => $id,
            'snapshots' => $snapshots,
            'count' => count($snapshots),
        ]);
    }

    private function handleAiRestore(ActApp $app): int
    {
        $data = $this->readInputData();
        $result = $app->restoreFromSnapshot($data);

        return $this->emitSuccess('restore', $result);
    }

    private function handleAiMergeStates(ActApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        $from = (int) ($data['from_index'] ?? 0);
        $to = (int) ($data['to_index'] ?? 0);
        $owner_id = (int) ($data['owner_id'] ?? 0);

        if ($id === '') {
            return $this->emitError('merge-states', 'VALIDATION_ERROR', 'Поле id обязательно');
        }
        if ($from < 1 || $to < 1) {
            return $this->emitError('merge-states', 'VALIDATION_ERROR', 'from_index и to_index обязательны');
        }
        if ($owner_id <= 0) {
            return $this->emitError('merge-states', 'VALIDATION_ERROR', 'owner_id обязателен для merge-states');
        }

        $result = $app->mergeStatesForOwner($owner_id, $id, $from, $to);

        return $this->emitSuccess('merge-states', $result);
    }

    private function handleAiValidate(ActApp $app): int
    {
        $data = $this->readInputData();
        $type = trim((string) ($data['type'] ?? ''));
        if ($type === '') {
            return $this->emitError('validate', 'VALIDATION_ERROR', 'Поле type обязательно');
        }

        $result = $app->validateAiPayload($type, $data);

        return $this->emitSuccess('validate', [
            'type' => $type,
            'result' => $result,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(array $data, string $key): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException("Поле {$key} обязательно");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function readInputData(): array
    {
        $raw_file_path = trim((string) $this->option('data-file'));
        if ($raw_file_path !== '') {
            if (! file_exists($raw_file_path)) {
                throw new \InvalidArgumentException("Файл данных не найден: {$raw_file_path}");
            }
            $raw = file_get_contents($raw_file_path);
            if ($raw === false) {
                throw new \InvalidArgumentException("Не удалось прочитать файл данных: {$raw_file_path}");
            }
        } else {
            $raw = (string) $this->option('data');
        }

        if ($raw === '' || $raw === '{}') {
            return [];
        }

        $decoded = Transformers::make()->fromJson($raw);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Ожидается валидный JSON-объект в --data или --data-file');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
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

    /**
     * @param  array<string, mixed>|null  $details
     */
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitJson(array $payload, int $exit_code): int
    {
        $json = Transformers::make()->toJson($payload, true, true);
        $this->line($json ?? '{}');

        return $exit_code;
    }
}
