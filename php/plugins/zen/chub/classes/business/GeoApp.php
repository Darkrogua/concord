<?php namespace Zen\Chub\Classes\Business;

use Illuminate\Support\Collection;
use Zen\Chub\Models\GeoAlias;
use Zen\Chub\Models\GeoObject;

class GeoApp
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Возвращает id geo-объектов по входному массиву.
     * Поддерживает scalar id и массивы с полями id|name|object_type.
     */
    public function idsByObjects(array $geo_objects, bool $create_missing = true): array
    {
        $ids = [];

        foreach ($geo_objects as $item) {
            $model = $this->resolveInputToModel($item, $create_missing);
            if ($model) {
                $ids[] = (int) $model->id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function create(array $data): GeoObject
    {
        return GeoObject::create($this->normalizePayload($data));
    }

    public function update(int $id, array $data): ?GeoObject
    {
        $model = $this->find($id);
        if (!$model) {
            return null;
        }

        $model->fill($this->normalizePayload($data));
        $model->save();

        return $model;
    }

    public function delete(int $id): bool
    {
        $model = $this->find($id);
        if (!$model) {
            return false;
        }

        return (bool) $model->delete();
    }

    public function find(int $id): ?GeoObject
    {
        if ($id <= 0) {
            return null;
        }

        return GeoObject::find($id);
    }

    /**
     * Дочерние объекты по родителю с опциональным фильтром по типу.
     */
    public function childrenByParent(
        int $parent_id,
        ?string $object_type = null,
        bool $only_active = false
    ): Collection {
        $parent = $this->find($parent_id);
        if (!$parent) {
            return collect();
        }

        $query = GeoObject::query()->where('parent_id', $parent->id)->orderBy('sort_order')->orderBy('name');

        if ($object_type !== null && trim($object_type) !== '') {
            $query->where('object_type', trim($object_type));
        }

        if ($only_active) {
            $query->where('active', 1);
        }

        return $query->get();
    }

    public function resolveOrCreate(array $payload): GeoObject
    {
        $resolved = $this->resolve($payload);
        if ($resolved) {
            return $resolved;
        }

        return $this->create($payload);
    }

    /**
     * Ищет гео-объект по имени города.
     * Если не найден — создает типовой объект с типом town.
     */
    public function resolveTownByNameOrCreate(string $name): ?GeoObject
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $resolved = $this->resolve([
            'name' => $name,
        ]);
        if ($resolved) {
            return $resolved;
        }

        return $this->create([
            'name' => $name,
            'object_type' => 'town',
            'active' => 1,
        ]);
    }

    public function resolve(array $payload): ?GeoObject
    {
        $id = $payload['id'] ?? null;
        if ($id !== null && $id !== '') {
            $model = $this->find((int) $id);
            if ($model) {
                return $model;
            }
        }

        $source_code = trim((string) ($payload['source_code'] ?? ''));
        $source_id = trim((string) ($payload['source_id'] ?? ''));
        if ($source_code !== '' && $source_id !== '') {
            $alias = GeoAlias::query()
                ->where('source_code', $source_code)
                ->where('source_id', $source_id)
                ->first();
            if ($alias) {
                $model = GeoObject::find((int) $alias->geo_object_id);
                if ($model) {
                    return $model;
                }
            }
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name !== '') {
            $query = GeoObject::query()->where('name', $name);

            $parent_id = $payload['parent_id'] ?? null;
            if ($parent_id !== null && $parent_id !== '') {
                $query->where('parent_id', (int) $parent_id);
            }

            $object_type = trim((string) ($payload['object_type'] ?? ''));
            if ($object_type !== '') {
                $query->where('object_type', $object_type);
            }

            $model = $query->first();
            if ($model) {
                return $model;
            }
        }

        return null;
    }

    public function attachAlias(
        int $geo_object_id,
        string $source_code,
        string $source_id,
        ?string $source_name = null,
        bool $active = true
    ): ?GeoAlias {
        $geo_object = $this->find($geo_object_id);
        if (!$geo_object) {
            return null;
        }

        return GeoAlias::updateOrCreate(
            [
                'source_code' => trim($source_code),
                'source_id' => trim($source_id),
            ],
            [
                'geo_object_id' => (int) $geo_object->id,
                'source_name' => $source_name ? trim($source_name) : null,
                'active' => $active ? 1 : 0,
            ]
        );
    }

    private function resolveInputToModel(mixed $item, bool $create_missing): ?GeoObject
    {
        if (is_int($item) || (is_string($item) && ctype_digit($item))) {
            return GeoObject::find((int) $item);
        }

        if (!is_array($item)) {
            return null;
        }

        $model = $this->resolve($item);
        if ($model || !$create_missing) {
            return $model;
        }

        return $this->create($item);
    }

    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('parent_id', $payload)) {
            $parent_id = $payload['parent_id'];
            $payload['parent_id'] = ($parent_id === '' || $parent_id === null) ? null : (int) $parent_id;
        }

        if (array_key_exists('active', $payload)) {
            $payload['active'] = $payload['active'] ? 1 : 0;
        }

        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('object_type', $payload)) {
            $payload['object_type'] = trim((string) $payload['object_type']);
        }

        return $payload;
    }
}
