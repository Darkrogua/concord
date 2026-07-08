<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\EntityApp;

/**
 * Реестр сущностей Chub (модель Entity): список, просмотр, CRUD через JSON.
 *
 * @link https://docs.octobercms.com/4.x/extend/console-commands.html
 */
class EntityCommand extends Command
{
    protected $signature = 'chub:entity
        {action : Подкоманда: help, list, show, create, update, delete}
        {id? : Числовой id для show, update, delete}
        {--data= : JSON-объект для create и update}
        {--code= : Код сущности (альтернатива id только для delete)}';

    protected $description = 'Реестр сущностей Zen.Chub (Entity): help, list, show, create, update, delete';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $app = EntityApp::make();

        try {
            return match ($action) {
                'help' => $this->emitJson($app->help()),
                'list' => $this->emitJson(['items' => $app->listActiveMinimal()]),
                'show' => $this->handleShow($app),
                'create' => $this->handleCreate($app),
                'update' => $this->handleUpdate($app),
                'delete' => $this->handleDelete($app),
                default => $this->unknownAction($action),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function handleShow(EntityApp $app): int
    {
        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для show укажите числовой id: php artisan chub:entity show 1');

            return self::FAILURE;
        }

        $row = $app->showFull($id);
        if ($row === null) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        return $this->emitJson($row);
    }

    private function handleCreate(EntityApp $app): int
    {
        $data = $this->requireDataOption();

        $model = $app->createFromData($data);

        return $this->emitJson([
            'ok' => true,
            'id' => (int) $model->id,
            'code' => (string) $model->code,
        ]);
    }

    private function handleUpdate(EntityApp $app): int
    {
        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для update укажите id: php artisan chub:entity update 5 --data=\'{...}\'');

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
            'code' => (string) $model->code,
        ]);
    }

    private function handleDelete(EntityApp $app): int
    {
        $code = trim((string) $this->option('code'));
        if ($code !== '') {
            $ok = $app->deleteByCode($code);
            if (!$ok) {
                $this->error("Запись с code=\"{$code}\" не найдена.");

                return self::FAILURE;
            }

            return $this->emitJson(['ok' => true, 'by' => 'code', 'code' => $code]);
        }

        $id = $this->intIdArgument();
        if ($id === null) {
            $this->error('Для delete укажите id или --code=');

            return self::FAILURE;
        }

        $ok = $app->deleteById($id);
        if (!$ok) {
            $this->error("Запись id={$id} не найдена.");

            return self::FAILURE;
        }

        return $this->emitJson(['ok' => true, 'by' => 'id', 'id' => $id]);
    }

    private function unknownAction(string $action): int
    {
        $this->error("Неизвестная подкоманда \"{$action}\". См. php artisan chub:entity help");

        return self::FAILURE;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function emitJson(array $data): int
    {
        $json = Transformers::make()->toJson($data, true, true);
        $this->line($json ?? '{}');

        return self::SUCCESS;
    }

    /**
     * @return array<string,mixed>
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
