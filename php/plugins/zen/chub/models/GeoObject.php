<?php namespace Zen\Chub\Models;

use Illuminate\Validation\Rule;
use Model;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Enums\GeoObjectType;

/**
 * Model
 */
class GeoObject extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_geo_objects';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'name' => 'required',
        'object_type' => 'required',
    ];

    public $fillable = [
        'name',
        'description',
        'parent_id',
        'object_type',
        'lat',
        'lon',
        'data',
        'active',
        'sort_order',
    ];

    public $hasMany = [
        'aliases' => [
            GeoAlias::class,
            'key' => 'geo_object_id',
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function scopeMenuEligible($query)
    {
        $types = array_values(array_filter(
            array_map(
                fn (GeoObjectType $type) => $type->isMenuEligible() ? $type->value : null,
                GeoObjectType::cases()
            )
        ));

        return $query->whereIn('object_type', $types);
    }

    public function beforeValidate(): void
    {
        $this->object_type = $this->normalizeObjectType($this->object_type ?? null);

        $this->rules['object_type'] = [
            'required',
            Rule::in(array_keys(GeoObjectType::options())),
        ];
    }

    public function beforeSave(): void
    {
        $this->validateParentRecursion();
    }

    /**
     * Устанавливает parent_id, преобразуя пустую строку в null.
     */
    public function setParentIdAttribute($value): void
    {
        $this->attributes['parent_id'] = ($value === '' || $value === null) ? null : (int) $value;
    }

    public function getObjectTypeOptions(): array
    {
        return GeoObjectType::options();
    }

    public function setDataAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['data'] = null;
            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            $value = [];
        }

        $this->attributes['data'] = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function getDataAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getDataEditorAttribute(): string
    {
        return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    public function setDataEditorAttribute(?string $value): void
    {
        $this->setDataAttribute($value);
    }

    public function getParentIdOptions(): array
    {
        $rows = self::query()
            ->orderBy('name')
            ->get(['id', 'name', 'object_type', 'parent_id']);

        $nodes_by_id = [];
        $children_by_parent = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $parent_id = $row->parent_id ? (int) $row->parent_id : 0;

            $nodes_by_id[$id] = [
                'id' => $id,
                'name' => (string) $row->name,
                'object_type' => (string) $row->object_type,
                'parent_id' => $parent_id,
            ];

            if (!isset($children_by_parent[$parent_id])) {
                $children_by_parent[$parent_id] = [];
            }

            $children_by_parent[$parent_id][] = $id;
        }

        // Исключаем текущий элемент и всех его потомков из списка потенциальных родителей.
        $excluded_ids = [];
        if ($this->id) {
            $queue = [(int) $this->id];
            while ($queue) {
                $current_id = array_shift($queue);
                if (isset($excluded_ids[$current_id])) {
                    continue;
                }
                $excluded_ids[$current_id] = true;

                foreach ($children_by_parent[$current_id] ?? [] as $child_id) {
                    $queue[] = (int) $child_id;
                }
            }
        }

        $options = ['' => '-- Верхний уровень --'];
        $visited = [];

        // Сначала нормальные корни.
        $stack = [];
        foreach ($children_by_parent[0] ?? [] as $root_id) {
            $stack[] = [$root_id, 0];
        }

        while ($stack) {
            [$id, $depth] = array_shift($stack);

            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;

            if (isset($excluded_ids[$id])) {
                continue;
            }

            $node = $nodes_by_id[$id] ?? null;
            if (!$node) {
                continue;
            }

            $prefix = str_repeat('— ', $depth);
            $type_label = GeoObjectType::labelByValue($node['object_type']);
            $options[$id] = $prefix . $type_label . ': ' . $node['name'];

            foreach ($children_by_parent[$id] ?? [] as $child_id) {
                $stack[] = [(int) $child_id, $depth + 1];
            }
        }

        // Добавляем оставшиеся узлы, которые не попали в обход от корней.
        foreach ($nodes_by_id as $id => $node) {
            if (isset($visited[$id]) || isset($excluded_ids[$id])) {
                continue;
            }

            $type_label = GeoObjectType::labelByValue($node['object_type']);
            $options[$id] = '[orphan] ' . $type_label . ': ' . $node['name'];
        }

        return $options;
    }

    private function validateParentRecursion(): void
    {
        $parent_id = $this->parent_id ? (int) $this->parent_id : null;
        if (!$parent_id) {
            return;
        }

        if ($this->id && $parent_id === (int) $this->id) {
            throw new ValidationException(['parent_id' => 'Нельзя выбрать текущий объект родителем самого себя.']);
        }

        $visited = [];
        $guard = 0;

        while ($parent_id && $guard++ < 1000) {
            if (isset($visited[$parent_id])) {
                throw new ValidationException(['parent_id' => 'Обнаружена рекурсия в дереве geo-объектов.']);
            }

            $visited[$parent_id] = true;

            if ($this->id && $parent_id === (int) $this->id) {
                throw new ValidationException(['parent_id' => 'Нельзя назначить потомка родителем текущего объекта.']);
            }

            $parent_row = static::query()->whereKey($parent_id)->first(['id', 'parent_id']);
            if (!$parent_row) {
                break;
            }

            $parent_id = $parent_row->parent_id ? (int) $parent_row->parent_id : null;
        }
    }

    private function normalizeObjectType(?string $value): string
    {
        $raw_value = trim((string) $value);
        if ($raw_value === '') {
            return GeoObjectType::PLACE->value;
        }

        return GeoObjectType::tryFrom($raw_value)?->value ?? GeoObjectType::PLACE->value;
    }
}
