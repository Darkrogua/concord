<?php namespace Zen\Chub\Models;

use Illuminate\Support\Str;
use Model;
use Zen\Chub\Classes\Support\Transformers;

/**
 * Model
 */
class Entity extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    public $attributeDefaults = [
        'icon_path' => 'plugins/zen/chub/assets/build/main/images/entities/cog.svg',
        'active' => 1,
    ];


    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_entities';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'name' => 'required',
        'code' => 'required|alpha_dash',
    ];

    /**
     * @var array fillable fields.
     */
    public $fillable = [
        'id',
        'name',
        'code',
        'url',
        'icon_path',
        'template_data',
        'sort_order',
        'parent_id',
        'active',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    private array $additional_fields = [];

    public function beforeValidate()
    {
        if (empty($this->code) && !empty($this->name)) {
            $this->code = Str::slug((string) $this->name);
        }

        $ignored_id = $this->id ?: 'NULL';
        $this->rules['code'] = 'required|alpha_dash|unique:zen_chub_entities,code,' . $ignored_id;
    }

    /**
     * Устанавливает parent_id, преобразуя пустую строку в null.
     */
    public function setParentIdAttribute($value)
    {
        $this->attributes['parent_id'] = ($value === '' || $value === null) ? null : (int) $value;
    }

    /**
     * Получает опции для выбора родителя.
     */
    public function getParentIdOptions(): array
    {
        // Получаем все элементы, исключая текущий
        $query = self::orderBy('name');
        if ($this->id) {
            $query->where('id', '<>', $this->id);
        }
        $items = $query->get();
        
        $options = ['' => '-- Верхний уровень --'];
        
        // Получаем все корневые элементы (без родителя)
        $root_items = $items->whereNull('parent_id');
        
        // Рекурсивно строим иерархию с отступами
        foreach ($root_items as $item) {
            $this->buildParentOptions($item, $items, $options, 0);
        }
        
        return $options;
    }

    /**
     * Рекурсивно строит опции для выбора родителя с отступами.
     */
    private function buildParentOptions($item, $all_items, &$options, int $depth): void
    {
        // Проверяем, не является ли элемент потомком текущего элемента
        if ($this->id) {
            $current = $item;
            while ($current && $current->parent_id) {
                if ($current->parent_id == $this->id) {
                    return; // Это потомок текущего элемента, пропускаем
                }
                $current = $all_items->where('id', $current->parent_id)->first();
                if (!$current) {
                    break;
                }
            }
        }
        
        $prefix = str_repeat('— ', $depth);
        $options[$item->id] = $prefix . $item->name;
        
        // Рекурсивно обрабатываем детей
        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
        }
    }

    /**
     * Устанавливает описание сущности.
     */
    public function setDescriptionAttribute(?string $text)
    {
        $this->additional_fields['description'] = $text;
    }

    /**
     * Возвращает описание сущности.
     */
    public function getDescriptionAttribute()
    {
        return $this->additional_fields['description'] ?? null;
    }

    /**
     * Перед сохранением собираем description из attributes в additional_fields.
     */
    public function beforeSave()
    {
        // Если description пришло из формы, сохраняем его в additional_fields
        if (isset($this->attributes['description'])) {
            $this->additional_fields['description'] = $this->attributes['description'];
            // Удаляем из attributes, чтобы не сохранялось в БД
            unset($this->attributes['description']);
        }
    }

    /**
     * Читает дополнительные поля из файла после загрузки из БД.
     */
    public function afterFetch()
    {
        if (!$this->code) {
            $this->additional_fields = [];
            return;
        }

        $file_path = base_path('plugins/zen/chub/data/entities/' . $this->code . '.json');
        
        // Если файл не существует, используем пустой массив
        if (!file_exists($file_path)) {
            $this->additional_fields = [];
            return;
        }

        $data = Transformers::make()->arrayFromFile($file_path);
        
        if (is_array($data)) {
            $this->additional_fields = $data;
        } else {
            $this->additional_fields = [];
        }
    }

    /**
     * Сохраняет дополнительные поля в файл.
     */
    public function afterSave()
    {
        if (!$this->code) {
            return;
        }

        $file_path = base_path('plugins/zen/chub/data/entities/' . $this->code . '.json');
        $dir_path = dirname($file_path);
        
        // Создаем директорию, если её нет
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        // Если additional_fields пустой и файл уже существует, не перезаписываем его
        // Это защищает от потери данных при пересборке через EntitiesScanner
        if (empty($this->additional_fields) && file_exists($file_path)) {
            return;
        }

        Transformers::make()->arrayToFile(
            $this->additional_fields,
            $file_path
        );
    }
}
