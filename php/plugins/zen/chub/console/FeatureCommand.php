<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\FeatureApp;

class FeatureCommand extends Command
{
    protected $signature = 'chub:feature
        {mode : Режим работы, поддерживается только ai}
        {action? : AI-подкоманда}
        {--data= : JSON-объект входных данных}
        {--data-file= : Абсолютный путь до JSON-файла с входными данными}';

    protected $description = 'AI-управление фичлистом (Feature / FeatureApp)';

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
                'Поддерживается только режим ai. Используйте: ./bin/artisan chub:feature ai <action>'
            );
        }

        if ($action === '') {
            return $this->emitError(
                'unknown',
                'UNKNOWN_ACTION',
                'Не указана AI-подкоманда. Начните с: ./bin/artisan chub:feature ai schema'
            );
        }

        $app = FeatureApp::make();

        try {
            return match ($action) {
                'schema' => $this->emitSuccess('schema', $app->aiSchema()),
                'list' => $this->handleAiList($app),
                'show' => $this->handleAiShow($app),
                'create' => $this->handleAiCreate($app),
                'update' => $this->handleAiUpdate($app),
                'move' => $this->handleAiMove($app),
                'link-dependency' => $this->handleAiLinkDependency($app),
                'unlink-dependency' => $this->handleAiUnlinkDependency($app),
                'delete' => $this->handleAiDelete($app),
                'validate-graph' => $this->emitSuccess('validate-graph', $app->validateDependencyGraph()),
                'validate' => $this->handleAiValidate($app),
                'export-data' => $this->handleAiExportData($app),
                'restore-data' => $this->handleAiRestoreData($app),
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

    private function handleAiList(FeatureApp $app): int
    {
        $filters = $this->readInputData();
        $items = $app->listForAi($filters);

        return $this->emitSuccess('list', [
            'items' => $items,
            'count' => count($items),
            'filters' => $filters,
        ]);
    }

    private function handleAiShow(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('show', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $row = $app->showFull($id);
        if ($row === null) {
            return $this->emitError('show', 'NOT_FOUND', "Фича id={$id} не найдена");
        }

        return $this->emitSuccess('show', ['feature' => $row]);
    }

    private function handleAiCreate(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $feature = $app->createFromAiData($data);

        return $this->emitSuccess('create', [
            'feature' => $app->showFull((string) $feature->id),
        ]);
    }

    private function handleAiUpdate(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('update', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $feature = $app->updateFromAiData($id, $data);
        if ($feature === null) {
            return $this->emitError('update', 'NOT_FOUND', "Фича id={$id} не найдена");
        }

        return $this->emitSuccess('update', [
            'feature' => $app->showFull($id),
        ]);
    }

    private function handleAiMove(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $feature = $app->moveFromAiData($data);

        return $this->emitSuccess('move', [
            'feature' => $app->showFull((string) $feature->id),
        ]);
    }

    private function handleAiLinkDependency(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $feature_id = trim((string) ($data['feature_id'] ?? ''));
        $depends_on_feature_id = trim((string) ($data['depends_on_feature_id'] ?? ''));

        $app->linkDependency(
            $feature_id,
            $depends_on_feature_id,
            isset($data['comment']) ? (string) $data['comment'] : null
        );

        return $this->emitSuccess('link-dependency', [
            'feature' => $app->showFull($feature_id),
        ]);
    }

    private function handleAiUnlinkDependency(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $feature_id = trim((string) ($data['feature_id'] ?? ''));
        $depends_on_feature_id = trim((string) ($data['depends_on_feature_id'] ?? ''));

        $removed = $app->unlinkDependency($feature_id, $depends_on_feature_id);

        return $this->emitSuccess('unlink-dependency', [
            'removed' => $removed,
            'feature' => $app->showFull($feature_id),
        ]);
    }

    private function handleAiDelete(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '') {
            return $this->emitError('delete', 'VALIDATION_ERROR', 'Поле id обязательно');
        }

        $force = (bool) ($data['force'] ?? false);
        $result = $app->deleteFeature($id, $force);

        return $this->emitSuccess('delete', $result);
    }

    private function handleAiValidate(FeatureApp $app): int
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

    private function handleAiExportData(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $path = trim((string) ($data['path'] ?? ''));
        $app->saveToDataFile($path !== '' ? $path : null);

        return $this->emitSuccess('export-data', [
            'path' => $path !== '' ? $path : FeatureApp::defaultDataFilePath(),
        ]);
    }

    private function handleAiRestoreData(FeatureApp $app): int
    {
        $data = $this->readInputData();
        $path = trim((string) ($data['path'] ?? ''));
        $count = $app->restoreFromDataFile($path !== '' ? $path : null);

        if ($count === 0) {
            $this->last_warnings[] = 'Файл данных пустой или не найден';

            return $this->emitSuccess('restore-data', [
                'restored_features' => 0,
                'path' => $path !== '' ? $path : FeatureApp::defaultDataFilePath(),
            ]);
        }

        return $this->emitSuccess('restore-data', [
            'restored_features' => $count,
            'path' => $path !== '' ? $path : FeatureApp::defaultDataFilePath(),
        ]);
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
