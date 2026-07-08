<?php namespace Zen\Chub\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Model;
use October\Rain\Exception\ValidationException;

/**
 * Фичлист: продуктовые фичи, дерево parent_id, граф зависимостей в pivot.
 */
class Feature extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    public const WORKFLOW_STATUSES = ['', 'in_progress', 'ready'];

    public $incrementing = false;

    protected $keyType = 'string';

    public $table = 'zen_chub_features';

    public $attributeDefaults = [
        'tags' => [],
        'acceptance_criteria' => [],
        'acceptance_checks' => [],
        'workflow_files' => [],
        'workflow_tags' => [],
        'workflow_status' => '',
    ];

    protected $jsonable = [
        'tags',
        'acceptance_criteria',
        'acceptance_checks',
        'workflow_files',
        'workflow_tags',
    ];

    public $rules = [
        'name' => 'required',
        'workflow_status' => 'in:,in_progress,ready',
    ];

    public $fillable = [
        'id',
        'parent_id',
        'name',
        'section_heading',
        'description',
        'feature_group',
        'tags',
        'acceptance_criteria',
        'acceptance_checks',
        'workflow_status',
        'workflow_comment',
        'workflow_files',
        'workflow_tags',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public $belongsToMany = [
        'dependencies' => [
            Feature::class,
            'table' => 'zen_chub_feature_dependencies',
            'key' => 'feature_id',
            'otherKey' => 'depends_on_feature_id',
            'pivot' => ['comment'],
        ],
    ];

    /** @var array<int, array{depends_on_feature_id: string, comment: string}>|null */
    private ?array $dependencies_sync_payload = null;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Sortable по умолчанию ставит sort_order = (int) id — для UUID не подходит.
     */
    public function initializeSortable(): void
    {
        $this->bindEvent('model.afterCreate', function (): void {
            $sort_order_column = $this->getSortOrderColumn();

            if ($this->{$sort_order_column} !== null) {
                return;
            }

            $max = (int) static::withoutGlobalScopes()->max($sort_order_column);
            $this->{$sort_order_column} = $max + 1;
            $this->saveQuietly();
        });
    }

    public function beforeValidate(): void
    {
        if ($this->parent_id && $this->id && (string) $this->parent_id === (string) $this->id) {
            throw new ValidationException(['parent_id' => 'Фича не может быть родителем самой себя.']);
        }
    }

    public function beforeSave(): void
    {
        unset(
            $this->attributes['dependencies_rows'],
            $this->attributes['acceptance_checks_panel'],
            $this->attributes['depended_by_panel'],
        );

        $this->tags = self::normalizeStringList(is_array($this->tags) ? $this->tags : []);
        $this->acceptance_criteria = self::normalizeStringList(
            is_array($this->acceptance_criteria) ? $this->acceptance_criteria : []
        );
        $this->acceptance_checks = is_array($this->acceptance_checks) ? $this->acceptance_checks : [];
        $this->workflow_files = self::normalizeWorkflowFiles(
            is_array($this->workflow_files) ? $this->workflow_files : []
        );
        $this->workflow_tags = self::normalizeStringList(
            is_array($this->workflow_tags) ? $this->workflow_tags : []
        );
        $this->workflow_status = in_array((string) ($this->workflow_status ?? ''), self::WORKFLOW_STATUSES, true)
            ? (string) $this->workflow_status
            : '';
    }

    public function afterSave(): void
    {
        if ($this->dependencies_sync_payload !== null) {
            $this->syncDependenciesFromPayload($this->dependencies_sync_payload);
            $this->dependencies_sync_payload = null;
        }
    }

    public function setParentIdAttribute(int|string|null $value): void
    {
        $value = is_string($value) ? trim($value) : $value;
        $this->attributes['parent_id'] = ($value === null || $value === '' || $value === 0) ? null : (string) $value;
    }

    /**
     * @param array<int, array{depends_on_feature_id: string, comment: string}>|null $rows
     */
    public function setDependenciesSyncPayload(?array $rows): void
    {
        $this->dependencies_sync_payload = $rows;
    }

    /**
     * Опции для repeater зависимостей (dropdown depends_on_feature_id).
     *
     * @return array<string, string>
     */
    public function getDependsOnFeatureIdOptions(): array
    {
        $query = self::orderBy('name');
        if ($this->id) {
            $query->where('id', '<>', $this->id);
        }

        $options = ['' => '-- Выберите фичу --'];

        foreach ($query->get() as $item) {
            $label = trim((string) ($item->name ?? ''));
            if ($item->feature_group) {
                $label .= $label !== '' ? ' · '.$item->feature_group : (string) $item->feature_group;
            }
            $options[(string) $item->id] = $label !== '' ? $label : (string) $item->id;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getParentIdOptions(): array
    {
        $query = self::orderBy('name');
        if ($this->id) {
            $query->where('id', '<>', $this->id);
        }

        $items = $query->get();
        $options = ['' => '-- Верхний уровень --'];
        $root_items = $items->whereNull('parent_id');

        foreach ($root_items as $item) {
            $this->buildParentOptions($item, $items, $options, 0);
        }

        return $options;
    }

    /**
     * @return array<int, array{depends_on_feature_id: string, comment: string}>
     */
    public function getDependenciesFormRows(): array
    {
        $rows = [];

        foreach ($this->dependencies()->orderBy('name')->get() as $dep) {
            $rows[] = [
                'depends_on_feature_id' => (string) $dep->id,
                'comment' => (string) ($dep->pivot->comment ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array{depends_on_feature_id?: string, comment?: string}> $rows
     */
    public function syncDependenciesFromPayload(array $rows): void
    {
        if (! $this->id) {
            return;
        }

        $sync = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $target_id = trim((string) ($row['depends_on_feature_id'] ?? ''));
            if ($target_id === '' || $target_id === (string) $this->id) {
                continue;
            }

            $sync[$target_id] = [
                'comment' => trim((string) ($row['comment'] ?? '')),
            ];
        }

        $this->dependencies()->sync($sync);
    }

    /**
     * @param array<int, mixed>|null $rows
     * @return array<int, string>
     */
    public static function normalizeStringList(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $text = is_array($row)
                ? trim((string) ($row['value'] ?? $row['tag'] ?? $row['name'] ?? ''))
                : trim((string) $row);
            if ($text === '') {
                continue;
            }
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $text;
        }

        return $result;
    }

    /**
     * @param array<int, mixed>|null $rows
     * @return array<int, array{id: string, path: string, note: string}>
     */
    public static function normalizeWorkflowFiles(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $files = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $path = trim((string) ($row['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = (string) Str::uuid();
            }

            $files[] = [
                'id' => $id,
                'path' => $path,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return $files;
    }

    /**
     * @param array<int|string, string> $options
     */
    private function buildParentOptions(self $item, Collection $all_items, array &$options, int $depth): void
    {
        if ($this->id) {
            $current = $item;
            while ($current && $current->parent_id) {
                if ((string) $current->parent_id === (string) $this->id) {
                    return;
                }

                $current = $all_items->where('id', $current->parent_id)->first();
                if (! $current) {
                    break;
                }
            }
        }

        $prefix = str_repeat('— ', $depth);
        $label = trim((string) ($item->name ?? ''));
        if ($item->feature_group) {
            $label = $label !== '' ? $label.' · '.$item->feature_group : (string) $item->feature_group;
        }
        $options[$item->id] = $prefix.$label;

        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
        }
    }

    /**
     * Опции фильтра «Слой» (distinct feature_group).
     *
     * @return array<string, string>
     */
    public function filterFeatureGroupOptions(): array
    {
        $groups = static::query()
            ->select('feature_group')
            ->distinct()
            ->whereNotNull('feature_group')
            ->where('feature_group', '!=', '')
            ->orderBy('feature_group')
            ->pluck('feature_group')
            ->all();

        $options = [];
        foreach ($groups as $group) {
            $options[(string) $group] = (string) $group;
        }

        return $options;
    }

    /**
     * Опции фильтра «Статус» workflow.
     *
     * @return array<string, string>
     */
    public function filterWorkflowStatusOptions(): array
    {
        return [
            '' => 'Не начата',
            'in_progress' => 'В работе',
            'ready' => 'Готова',
        ];
    }

    /**
     * Опции фильтра «Теги» (мультивыбор) из каталога.
     *
     * @return array<string, string>
     */
    public function filterCatalogTagOptions(): array
    {
        return self::distinctTagsFromColumn('tags');
    }

    /**
     * Подсказки для taglist «Теги workflow».
     *
     * @return array<string, string>
     */
    public function filterWorkflowTagOptions(): array
    {
        return self::distinctTagsFromColumn('workflow_tags');
    }

    /**
     * @return array<string, string>
     */
    private static function distinctTagsFromColumn(string $column): array
    {
        $tags = [];

        foreach (static::query()->whereNotNull($column)->pluck($column) as $raw) {
            $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (! is_array($list)) {
                continue;
            }

            foreach ($list as $tag) {
                if (is_array($tag)) {
                    $tag = trim((string) ($tag['value'] ?? $tag['tag'] ?? $tag['name'] ?? ''));
                } else {
                    $tag = trim((string) $tag);
                }
                if ($tag !== '') {
                    $tags[$tag] = $tag;
                }
            }
        }

        ksort($tags, SORT_NATURAL | SORT_FLAG_CASE);

        return $tags;
    }

    /**
     * Фильтр по тегам каталога (OR: любая из выбранных меток).
     *
     * @param \Illuminate\Database\Eloquent\Builder<self> $query
     * @param array<int|string, string>|string|null $tags
     */
    public function scopeFilterCatalogTags($query, $tags, $mode = null): void
    {
        if (! is_array($tags) || $tags === []) {
            return;
        }

        $selected = [];
        foreach (array_values($tags) as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $selected[] = $tag;
            }
        }

        if ($selected === []) {
            return;
        }

        $driver = $query->getConnection()->getDriverName();

        $query->where(function ($q) use ($selected, $driver): void {
            foreach ($selected as $tag) {
                if ($driver === 'pgsql') {
                    $q->orWhereRaw('tags::jsonb @> ?::jsonb', [json_encode([$tag], JSON_UNESCAPED_UNICODE)]);
                } else {
                    $q->orWhere('tags', 'like', '%"'.addcslashes($tag, '"\\').'"%');
                }
            }
        });
    }
}
