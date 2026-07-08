<?php namespace Zen\Chub\Models;

use Model;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Support\Transformers;

/**
 * Model
 */
class Document extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    protected static function boot()
    {
        parent::boot();

        static::extend(function ($model) {
            $model->bindEvent('model.beforeSetAttribute', function ($key, $value) {
                if ($key !== 'props' || ! is_string($value)) {
                    return null;
                }

                $trimmed = trim($value);
                if ($trimmed === '') {
                    return [];
                }

                $decoded = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    return [];
                }

                return $decoded;
            });
        });
    }

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_documents';

    /**
     * @var array<string, mixed>
     */
    public $attributeDefaults = [
        'props' => [],
    ];

    /**
     * JSON в БД для репитера свойств.
     *
     * @var array<int, string>
     */
    protected $jsonable = [
        'props',
    ];

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'name' => 'required',
        'code' => ['required', 'regex:/^[\p{L}\p{N}_-]+$/u'],
    ];

    public $fillable = [
        'id',
        'name',
        'code',
        'props',
        'parent_id',
        'is_folder',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Временное хранилище markdown-контента (файл, не БД).
     */
    private ?string $data_markdown = null;

    /**
     * Состояние узла до сохранения для переноса файла/папки при смене code/parent.
     *
     * @var array{path:string,is_folder:bool}|null
     */
    private ?array $storage_previous_state = null;

    public function getDataAttribute($value = null): string
    {
        if ((bool) ($this->is_folder ?? false)) {
            return '';
        }

        if ($this->id) {
            $file_path = $this->getStoragePath();
            if (is_file($file_path)) {
                $content = file_get_contents($file_path);
                if ($content === false) {
                    return '';
                }

                $parsed = Transformers::make()->parseMarkdownFrontmatter($content);

                return $parsed['body'];
            }
        }

        return $this->data_markdown ?? '';
    }

    public function setDataAttribute($value): void
    {
        $this->data_markdown = is_string($value) ? $value : '';
        $this->attributes['data'] = $this->data_markdown;
    }

    public function beforeSave(): void
    {
        $this->captureStoragePreviousState();
        $this->normalizeCode();
        $this->enforceFolderIrreversible();

        unset($this->attributes['data']);
    }

    public function beforeValidate(): void
    {
        $this->normalizeCode();
        $this->assertCodeUniqueInParent();
    }

    public function afterSave(): void
    {
        $this->syncStorageNode();

        if ((bool) ($this->is_folder ?? false)) {
            return;
        }

        $file_path = $this->getStoragePath();
        $body = $this->data_markdown;
        if ($body === null && is_file($file_path)) {
            $full = file_get_contents($file_path);
            if ($full !== false) {
                $body = Transformers::make()->parseMarkdownFrontmatter($full)['body'];
            }
        }

        if ($body === null) {
            $body = '';
        }

        $file_contents = $this->composeMarkdownFileWithFrontmatter((string) $body);
        file_put_contents($file_path, $file_contents);
    }

    public function afterDelete(): void
    {
        $storage_path = $this->getStoragePath();

        if ((bool) ($this->is_folder ?? false)) {
            if (is_dir($storage_path)) {
                @rmdir($storage_path);
            }
            return;
        }

        if (is_file($storage_path)) {
            @unlink($storage_path);
        }
    }

    public function setParentIdAttribute($value): void
    {
        $this->attributes['parent_id'] = ($value === '' || $value === null) ? null : (int) $value;
    }

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

    public function getStoragePath(): string
    {
        return $this->getStoragePathBySnapshot([
            'id' => $this->id,
            'code' => $this->code,
            'parent_id' => $this->parent_id,
            'is_folder' => (bool) ($this->is_folder ?? false),
        ]);
    }

    private function buildParentOptions($item, $all_items, array &$options, int $depth): void
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
        $options[$item->id] = $prefix . $item->name;

        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
        }
    }

    private function captureStoragePreviousState(): void
    {
        if (!$this->id) {
            $this->storage_previous_state = null;
            return;
        }

        $original = self::query()->find($this->id);
        if (!$original) {
            $this->storage_previous_state = null;
            return;
        }

        $snapshot = [
            'id' => $original->id,
            'code' => $original->code,
            'parent_id' => $original->parent_id,
            'is_folder' => (bool) ($original->is_folder ?? false),
        ];

        $this->storage_previous_state = [
            'path' => $this->getStoragePathBySnapshot($snapshot),
            'is_folder' => (bool) ($snapshot['is_folder'] ?? false),
        ];
    }

    private function normalizeCode(): void
    {
        $code = trim((string) ($this->code ?? ''));
        $code = preg_replace('/\s+/u', '-', $code);
        $code = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $code);
        $code = preg_replace('/-+/u', '-', (string) $code);
        $code = trim((string) $code, '-_');
        $this->code = $code;
        $this->attributes['code'] = $code;
    }

    private function assertCodeUniqueInParent(): void
    {
        $code = trim((string) ($this->code ?? ''));
        if ($code === '') {
            return;
        }

        $parent_id = $this->parent_id === null || $this->parent_id === '' ? null : (int) $this->parent_id;

        $query = self::query()->where('code', $code);
        if ($parent_id === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parent_id);
        }

        if ($this->id) {
            $query->where('id', '<>', $this->id);
        }

        if ($query->exists()) {
            throw new ValidationException([
                'code' => 'Код должен быть уникальным внутри выбранной папки.',
            ]);
        }
    }

    private function enforceFolderIrreversible(): void
    {
        if ((bool) ($this->is_folder ?? false)) {
            return;
        }

        if (!$this->id) {
            return;
        }

        $original_is_folder = (bool) self::query()
            ->where('id', $this->id)
            ->value('is_folder');

        if ($original_is_folder) {
            $this->is_folder = 1;
            $this->attributes['is_folder'] = 1;
        }
    }

    private function syncStorageNode(): void
    {
        $root_path = $this->getStorageRootPath();
        if (!is_dir($root_path)) {
            mkdir($root_path, 0755, true);
        }

        $current_path = $this->getStoragePath();
        $previous_path = $this->storage_previous_state['path'] ?? null;

        if ($previous_path && $previous_path !== $current_path && (file_exists($previous_path) || is_dir($previous_path))) {
            $target_parent = dirname($current_path);
            if (!is_dir($target_parent)) {
                mkdir($target_parent, 0755, true);
            }

            @rename($previous_path, $current_path);
        }

        if ((bool) ($this->is_folder ?? false)) {
            if (!is_dir($current_path)) {
                mkdir($current_path, 0755, true);
            }
            return;
        }

        $target_parent = dirname($current_path);
        if (!is_dir($target_parent)) {
            mkdir($target_parent, 0755, true);
        }

        if (!is_file($current_path)) {
            file_put_contents($current_path, '');
        }
    }

    private function getStorageRootPath(): string
    {
        return base_path('plugins/zen/chub/data/documents');
    }

    private function getStoragePathBySnapshot(array $snapshot): string
    {
        $code = trim((string) ($snapshot['code'] ?? ''));
        $base_path = $this->getStorageRootPath();

        if ($code === '') {
            return $base_path;
        }

        $parent_id = isset($snapshot['parent_id']) ? (int) $snapshot['parent_id'] : 0;
        $ancestor_segments = $this->collectAncestorCodes($parent_id);
        $segments = array_merge($ancestor_segments, [$code]);
        $relative_path = implode('/', $segments);

        if ((bool) ($snapshot['is_folder'] ?? false)) {
            return $base_path . '/' . $relative_path;
        }

        return $base_path . '/' . $relative_path . '.md';
    }

    /**
     * @return array<int, string>
     */
    private function collectAncestorCodes(int $parent_id): array
    {
        $segments = [];
        $visited = [];
        $current_parent_id = $parent_id;

        while ($current_parent_id > 0) {
            if (isset($visited[$current_parent_id])) {
                break;
            }

            $visited[$current_parent_id] = true;
            $parent = self::query()->find($current_parent_id);
            if (!$parent) {
                break;
            }

            $code = trim((string) ($parent->code ?? ''));
            if ($code !== '') {
                $segments[] = $code;
            }

            $current_parent_id = (int) ($parent->parent_id ?? 0);
        }

        return array_reverse($segments);
    }

    /**
     * YAML frontmatter (ассоц. массив) → строки репитера для формы.
     *
     * @param array<string, mixed>|null $yaml
     * @return array<int, array{key: string, value_text: string, value_tags: array<int, string>}>
     */
    public static function yamlArrayToPropsRepeater(?array $yaml): array
    {
        if ($yaml === null || $yaml === []) {
            return [];
        }

        $rows = [];

        foreach ($yaml as $key => $value) {
            $key_string = (string) $key;
            $row = [
                'key' => $key_string,
                'value_text' => '',
                'value_tags' => [],
            ];

            if (is_array($value)) {
                if (self::isAssocArray($value)) {
                    $row['value_text'] = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $row['value_tags'] = array_map('strval', $value);
                }
            } else {
                $row['value_text'] = $value === null ? '' : (string) $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @return array<string, mixed>
     */
    private function propsRepeaterToYamlAssoc(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $assoc = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $tags = $row['value_tags'] ?? [];
            if (is_string($tags) && $tags !== '') {
                $tags = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $tags) ?: [])));
            }
            if (!is_array($tags)) {
                $tags = [];
            }

            if ($tags !== []) {
                $assoc[$key] = array_values(array_map('strval', $tags));

                continue;
            }

            $text = (string) ($row['value_text'] ?? '');
            $trimmed_text = trim($text);
            if ($trimmed_text !== '') {
                $decoded = json_decode($trimmed_text, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $assoc[$key] = $decoded;

                    continue;
                }
            }

            $assoc[$key] = $text;
        }

        return $assoc;
    }

    private function composeMarkdownFileWithFrontmatter(string $body): string
    {
        $rows = $this->props;
        $assoc = [];
        if (is_array($rows)) {
            $assoc = $this->propsRepeaterToYamlAssoc($rows);
        }

        $yaml_string = Transformers::make()->toYaml($assoc);
        if ($yaml_string === null || $yaml_string === '') {
            return $body;
        }

        return "---\n" . $yaml_string . "\n---\n\n" . ltrim($body, "\n");
    }

    /**
     * @param array<mixed> $value
     */
    private static function isAssocArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return ! array_is_list($value);
    }
}
