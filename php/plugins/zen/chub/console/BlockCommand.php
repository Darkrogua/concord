<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\BlockApp;

/**
 * Реестр UI-блоков Chub (модель Block): список, просмотр, CRUD через JSON.
 */
class BlockCommand extends Command
{
    protected $signature = 'chub:block
        {action : Подкоманда: help, list, show, create, update, delete, import, import-all, export-data, restore-data, lint-dom-ids}
        {id? : Числовой id для show, update, delete; code для import}
        {--data= : JSON-объект для create и update}
        {--no-bootstrap : Для import/import-all — не создавать недостающие .md/.variants.json/.json}';

    protected $description = 'Реестр UI-блоков Zen.Chub (Block): CRUD, import, export/restore';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $app = BlockApp::make();

        try {
            return match ($action) {
                'help' => $this->emitJson($app->help()),
                'list' => $this->emitJson(['items' => $app->listMinimal()]),
                'show' => $this->handleShow($app),
                'create' => $this->handleCreate($app),
                'update' => $this->handleUpdate($app),
                'delete' => $this->handleDelete($app),
                'import' => $this->handleImport($app),
                'import-all' => $this->handleImportAll($app),
                'export-data' => $this->handleExportData($app),
                'restore-data' => $this->handleRestoreData($app),
                'lint-dom-ids' => $this->handleLintDomIds($app),
                default => $this->unknownAction($action),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function handleShow(BlockApp $app): int
    {
        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для show укажите числовой id: php artisan chub:block show 1');

            return self::FAILURE;
        }

        $row = $app->showFull($id);
        if ($row === null) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        return $this->emitJson($row);
    }

    private function handleCreate(BlockApp $app): int
    {
        $data = $this->requireDataOption();

        $model = $app->createFromData($data);

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) ($model->code ?? ''),
            'name' => (string) ($model->name ?? ''),
        ]);
    }

    private function handleUpdate(BlockApp $app): int
    {
        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для update укажите id: php artisan chub:block update 5 --data=\'{...}\'');

            return self::FAILURE;
        }

        $data = $this->requireDataOption();

        $model = $app->updateFromData($id, $data);
        if ($model === null) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) ($model->code ?? ''),
            'name' => (string) ($model->name ?? ''),
        ]);
    }

    private function handleImport(BlockApp $app): int
    {
        $code = trim((string) $this->argument('id'));
        if ($code === '') {
            $this->error('Для import укажите code: php artisan chub:block import header');

            return self::FAILURE;
        }

        $model = $app->importFromTheme($code, !$this->option('no-bootstrap'));

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) ($model->code ?? ''),
            'name' => (string) ($model->name ?? ''),
            'files_count' => count(is_array($model->files) ? $model->files : []),
        ]);
    }

    private function handleImportAll(BlockApp $app): int
    {
        $result = $app->importAllFromTheme(!$this->option('no-bootstrap'));

        return $this->emitJson([
            'ok' => true,
            'imported_count' => count($result['imported']),
            'error_count' => count($result['errors']),
            'imported' => $result['imported'],
            'errors' => $result['errors'],
        ]);
    }

    private function handleExportData(BlockApp $app): int
    {
        $result = $app->exportAllToTheme();

        return $this->emitJson([
            'ok' => true,
            'blocks' => $result['blocks'],
            'folders' => $result['folders'],
        ]);
    }

    private function handleRestoreData(BlockApp $app): int
    {
        $result = $app->restoreAllFromTheme();

        return $this->emitJson([
            'ok' => true,
            'restored' => $result['restored'],
            'blocks' => $result['blocks'],
            'folders' => $result['folders'],
        ]);
    }

    private function handleLintDomIds(BlockApp $app): int
    {
        $sync = new \Zen\Chub\Classes\System\BlocksSync();
        $result = $sync->lintDomIds();

        return $this->emitJson($result);
    }

    private function handleDelete(BlockApp $app): int
    {
        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для delete укажите id: php artisan chub:block delete 1');

            return self::FAILURE;
        }

        $ok = $app->deleteById($id);
        if (!$ok) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        return $this->emitJson(['ok' => true, 'id' => $id]);
    }

    private function unknownAction(string $action): int
    {
        $this->error("Неизвестная подкоманда \"{$action}\". См. php artisan chub:block help");

        return self::FAILURE;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emitJson(array $data): int
    {
        $json = Transformers::make()->toJson($data, true, true);
        $this->line($json ?? '{}');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDataOption(): array
    {
        $raw = (string) $this->option('data');
        $decoded = Transformers::make()->fromJson($raw);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Ожидается валидный JSON-объект в --data=');
        }

        return $decoded;
    }

    private function intIdArgument(): ?int
    {
        $raw = $this->argument('id');
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
