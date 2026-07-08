<?php namespace Zen\Chub\Classes\System;

use Illuminate\Support\Str;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\Tools\EntityBuilder;
use Zen\Chub\Models\Entity;

/**
 * Консольный и программный доступ к реестру сущностей (модель Entity).
 */
class EntityApp
{
    private const BUILD_FLAG_KEYS = ['build_entity', 'generate'];

    private const BUILDER_OPTION_KEYS = [
        'builder_name',
        'builder_name_plural',
        'builder_name_title',
        'builder_name_create',
        'builder_name_update',
    ];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Справка по подкомандам и полезная схема JSON для create/update.
     */
    public function help(): array
    {
        return [
            'commands' => [
                [
                    'name' => 'help',
                    'usage' => 'php artisan chub:entity help',
                    'description' => 'Эта справка в JSON.',
                ],
                [
                    'name' => 'list',
                    'usage' => 'php artisan chub:entity list',
                    'description' => 'Краткий список только активных сущностей (active=1).',
                ],
                [
                    'name' => 'show',
                    'usage' => 'php artisan chub:entity show {id}',
                    'description' => 'Полные данные записи по id: колонки БД, template_data как объект, файл data/entities/{code}.json.',
                ],
                [
                    'name' => 'create',
                    'usage' => 'php artisan chub:entity create --data=\'{...}\'',
                    'description' => 'Создание записи. См. поле payload ниже.',
                ],
                [
                    'name' => 'update',
                    'usage' => 'php artisan chub:entity update {id} --data=\'{...}\'',
                    'description' => 'Изменение полей записи по id.',
                ],
                [
                    'name' => 'delete',
                    'usage' => 'php artisan chub:entity delete {id} | php artisan chub:entity delete --code=my_code',
                    'description' => 'Удаление записи; при наличии template_data — откат EntityBuilder и удаление сгенерированных файлов; удаляется data/entities/{code}.json.',
                ],
            ],
            'payload_create_update' => [
                'name' => 'string, обязательно при создании',
                'code' => 'string, alpha_dash; пусто — slug из name',
                'url' => 'string|null',
                'icon_path' => 'string|null',
                'parent_id' => 'int|null',
                'sort_order' => 'int|null',
                'active' => '0|1',
                'description' => 'string|null → файл data/entities/{code}.json',
                'template_data' => 'object|string|null',
                'build_entity' => 'bool (или generate) — после сохранения вызвать EntityBuilder::create',
                'builder_name' => 'string, опционально (по умолчанию Studly от code/name)',
                'builder_name_plural' => 'string, опционально',
                'builder_name_title' => 'string, по умолчанию «Записи»',
                'builder_name_create' => 'string',
                'builder_name_update' => 'string',
            ],
        ];
    }

    /**
     * @return list<array{id:int,code:string,name:string,parent_id:int|null,url:?string,sort_order:int|null}>
     */
    public function listActiveMinimal(): array
    {
        return Entity::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Entity $e) {
                return [
                    'id' => (int) $e->id,
                    'code' => (string) $e->code,
                    'name' => (string) $e->name,
                    'parent_id' => $e->parent_id !== null ? (int) $e->parent_id : null,
                    'url' => $e->url,
                    'sort_order' => $e->sort_order !== null ? (int) $e->sort_order : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Полный снимок: атрибуты БД + расшифрованный template_data + содержимое JSON по коду.
     *
     * @return array<string,mixed>|null
     */
    public function showFull(int $id): ?array
    {
        $entity = Entity::find($id);
        if (!$entity) {
            return null;
        }

        $attrs = $entity->getAttributes();
        $attrs['description'] = $entity->description;
        $attrs['template_data'] = $this->decodeTemplateData($attrs['template_data'] ?? null);
        $attrs['extra'] = $this->readEntityDataFile((string) $entity->code);

        return $attrs;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function createFromData(array $data): Entity
    {
        [$payload, $build, $builder] = $this->splitBuildOptions($data);

        $model = new Entity();
        $this->applyPayload($model, $payload, true);

        try {
            $model->save();
        } catch (ValidationException $e) {
            throw new \RuntimeException('Валидация: ' . $e->getMessage(), 0, $e);
        }

        if ($build) {
            $this->runEntityBuilderAfterSave($model, $builder);
        }

        return $model->fresh() ?? $model;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateFromData(int $id, array $data): ?Entity
    {
        $model = Entity::find($id);
        if (!$model) {
            return null;
        }

        [$payload, $build, $builder] = $this->splitBuildOptions($data);

        $this->applyPayload($model, $payload, false);

        try {
            $model->save();
        } catch (ValidationException $e) {
            throw new \RuntimeException('Валидация: ' . $e->getMessage(), 0, $e);
        }

        if ($build) {
            $this->runEntityBuilderAfterSave($model, $builder);
        }

        return $model->fresh() ?? $model;
    }

    public function deleteById(int $id): bool
    {
        $model = Entity::find($id);

        return $model ? $this->deleteModel($model) : false;
    }

    public function deleteByCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $model = Entity::query()->where('code', $code)->first();

        return $model ? $this->deleteModel($model) : false;
    }

    private function deleteModel(Entity $model): bool
    {
        $this->removeGeneratedStack($model);
        $this->deleteEntityDataFile((string) $model->code);

        return (bool) $model->delete();
    }

    private function removeGeneratedStack(Entity $model): void
    {
        $template_data = json_decode((string) ($model->template_data ?? ''), true);
        if (!is_array($template_data)) {
            return;
        }

        $builder_name = trim((string) ($template_data['builder_name'] ?? ''));
        $builder_name_plural = trim((string) ($template_data['builder_name_plural'] ?? ''));

        if ($builder_name === '' || $builder_name_plural === '') {
            return;
        }

        EntityBuilder::make()->remove($builder_name, $builder_name_plural);
    }

    private function deleteEntityDataFile(string $code): void
    {
        if ($code === '') {
            return;
        }

        $path = base_path('plugins/zen/chub/data/entities/' . $code . '.json');
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array{0: array<string,mixed>, 1: bool, 2: array<string,string>}
     */
    private function splitBuildOptions(array $data): array
    {
        $build = false;
        foreach (self::BUILD_FLAG_KEYS as $key) {
            if (!empty($data[$key])) {
                $build = true;
                break;
            }
        }

        $builder = [];
        foreach (self::BUILDER_OPTION_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $builder[$key] = (string) $data[$key];
            }
        }

        $payload = $data;
        foreach (array_merge(self::BUILD_FLAG_KEYS, self::BUILDER_OPTION_KEYS) as $key) {
            unset($payload[$key]);
        }

        return [$payload, $build, $builder];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function applyPayload(Entity $model, array $payload, bool $isCreate): void
    {
        $allowed = array_flip([
            'name',
            'code',
            'url',
            'icon_path',
            'template_data',
            'sort_order',
            'parent_id',
            'active',
        ]);

        foreach ($payload as $key => $value) {
            if ($key === 'description') {
                $model->description = $value === null ? null : (string) $value;

                continue;
            }

            if (!isset($allowed[$key])) {
                continue;
            }

            if ($key === 'template_data' && is_array($value)) {
                $model->template_data = json_encode($value, JSON_UNESCAPED_UNICODE);

                continue;
            }

            $model->{$key} = $value;
        }

        if ($isCreate && empty($model->name)) {
            throw new \InvalidArgumentException('Поле name обязательно при создании.');
        }
    }

    /**
     * @param  array<string,string>  $builderPost
     */
    private function runEntityBuilderAfterSave(Entity $model, array $builderPost): void
    {
        $entity_code = trim((string) ($model->code ?? ''));
        $entity_name = trim((string) ($model->name ?? ''));
        $fallback_name = $entity_code !== '' ? $entity_code : $entity_name;

        $builder_name = trim((string) ($builderPost['builder_name'] ?? ''));
        if ($builder_name === '') {
            $builder_name = Str::studly($fallback_name);
        }

        $builder_name_plural = trim((string) ($builderPost['builder_name_plural'] ?? ''));
        if ($builder_name_plural === '') {
            $builder_name_plural = Str::plural(Str::snake($builder_name));
        }

        $builder_name_title = trim((string) ($builderPost['builder_name_title'] ?? 'Записи'));
        $builder_name_create = trim((string) ($builderPost['builder_name_create'] ?? 'Создать запись'));
        $builder_name_update = trim((string) ($builderPost['builder_name_update'] ?? 'Изменить запись'));

        $template_data_payload = [
            'builder_name' => $builder_name,
            'builder_name_plural' => $builder_name_plural,
            'builder_name_title' => $builder_name_title,
            'builder_name_create' => $builder_name_create,
            'builder_name_update' => $builder_name_update,
        ];

        $model->template_data = json_encode($template_data_payload, JSON_UNESCAPED_UNICODE);
        $model->save();

        EntityBuilder::make()->create(
            $builder_name,
            $builder_name_plural,
            $builder_name_title,
            $builder_name_create,
            $builder_name_update,
        );
    }

    private function decodeTemplateData(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : $raw;
    }

    /**
     * @return array<string,mixed>
     */
    private function readEntityDataFile(string $code): array
    {
        if ($code === '') {
            return [];
        }

        $file_path = base_path('plugins/zen/chub/data/entities/' . $code . '.json');
        if (!is_file($file_path)) {
            return [];
        }

        $data = Transformers::make()->arrayFromFile($file_path);

        return is_array($data) ? $data : [];
    }
}
