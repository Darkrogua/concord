<?php namespace Zen\Chub\Models;

use Illuminate\Database\Eloquent\Collection;
use Model;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\BlockPaths;

/**
 * Фронтенд-блоки: реестр partials темы (code → папка partials/{group}/).
 * is_folder — только иконка в списке. description — файл {name}.md, не БД.
 */
class Block extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    protected static function boot()
    {
        parent::boot();

        static::extend(function (self $model): void {
            $model->bindEvent('model.beforeSetAttribute', function (string $key, mixed $value): ?array {
                if ($key !== 'files' || ! is_string($value)) {
                    return null;
                }

                return Transformers::make()->fromJson($value) ?? [];
            });
        });
    }

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_blocks';

    /**
     * @var array<string, mixed>
     */
    public $attributeDefaults = [
        'files' => [],
    ];

    /**
     * @var array<int, string>
     */
    protected $jsonable = [
        'files',
    ];

    /**
     * @var array<string, mixed>
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'code',
        'name',
        'parent_id',
        'is_folder',
        'files',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    private ?string $description_markdown = null;

    public function beforeValidate(): void
    {
        if ((bool) ($this->is_folder ?? false)) {
            $this->rules['code'] = 'nullable';
            $this->code = null;
            $this->attributes['code'] = null;

            return;
        }

        $block_id = $this->id ?: 'NULL';
        $this->rules['code'] = "required|unique:zen_chub_blocks,code,{$block_id},id|regex:/^[a-z0-9_\\-]+(?:\\/[a-z0-9_\\-]+)?\$/i";
    }

    public function beforeSave(): void
    {
        unset($this->attributes['description']);

        if ((bool) ($this->is_folder ?? false)) {
            $this->code = null;
            $this->attributes['code'] = null;
        } elseif (is_string($this->code)) {
            $this->code = trim($this->code);
            $this->attributes['code'] = $this->code !== '' ? $this->code : null;
        }

        $this->files = self::normalizeFiles(is_array($this->files) ? $this->files : []);
    }

    public function afterSave(): void
    {
        if ($this->description_markdown === null) {
            return;
        }

        $code = trim((string) ($this->code ?? ''));
        if ($code === '' || (bool) ($this->is_folder ?? false)) {
            $this->description_markdown = null;

            return;
        }

        BlockPaths::make()->writeDescription($code, $this->description_markdown);
        $this->description_markdown = null;
    }

    /**
     * Описание блока из co-located markdown-файла.
     */
    public function getDescriptionAttribute($value = null): string
    {
        if ((bool) ($this->is_folder ?? false)) {
            return '';
        }

        $code = trim((string) ($this->code ?? ''));
        if ($code !== '') {
            return BlockPaths::make()->readDescription($code);
        }

        return $this->description_markdown ?? '';
    }

    public function setDescriptionAttribute($value): void
    {
        $this->description_markdown = is_string($value) ? $value : '';
    }

    /**
     * Устанавливает parent_id, преобразуя пустую строку в null.
     */
    public function setParentIdAttribute(int|string|null $value): void
    {
        $this->attributes['parent_id'] = $value ? (int) $value : null;
    }

    public function setCodeAttribute(int|string|null $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['code'] = null;

            return;
        }

        $this->attributes['code'] = trim((string) $value);
    }

    /**
     * Опции выбора родителя с иерархическими отступами.
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
     * @param array<int, mixed>|null $rows
     * @return array<int, array{name: string, description: string}>
     */
    public static function normalizeFiles(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $files = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim(str_replace('\\', '/', (string) ($row['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $files[] = [
                'name' => $name,
                'description' => trim((string) ($row['description'] ?? '')),
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
                if ((int) $current->parent_id === (int) $this->id) {
                    return;
                }

                $current = $all_items->where('id', $current->parent_id)->first();
                if (!$current) {
                    break;
                }
            }
        }

        $prefix = str_repeat('— ', $depth);
        $label = trim((string) ($item->name ?? ''));
        if ($item->code) {
            $label = $label !== '' ? $label.' ('.$item->code.')' : (string) $item->code;
        }
        $options[$item->id] = $prefix.$label;

        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
        }
    }
}
