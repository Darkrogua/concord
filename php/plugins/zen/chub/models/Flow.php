<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\ProcessApp;

/**
 * Потоки и папки в UI: папки (is_folder) не имеют flow_{code}.json, только группировка в списке.
 */
class Flow extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_flows';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'code' => 'required',
    ];

    public $fillable = [
        'code',
        'name',
        'description',
        'data',
        'sort_order',
        'process_app',
        'parent_id',
        'is_folder',
    ];

    public $purgeable = [
        'process_app',
    ];

    public function beforeValidate(): void
    {
        $flow_id = $this->id ?: 'NULL';
        $this->rules['code'] = "required|unique:zen_chub_flows,code,{$flow_id},id";
    }

    public function beforeSave(): void
    {
        $this->enforceFolderIrreversible();
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

    public function setProcessAppAttribute($value): void
    {
        if ((bool) ($this->is_folder ?? false)) {
            return;
        }

        if (!$this->code) {
            return;
        }

        $flow_data = $value;
        if (is_string($value) && $value !== '') {
            $flow_data = json_decode($value, true);
        }

        if (!is_array($flow_data)) {
            $flow_data = [];
        }

        $process_app = new ProcessApp($this);

        try {
            $process_app->updateFlow($flow_data);
        } catch (\Throwable $exception) {
            throw $exception;
        }
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
        $label = trim((string) ($item->name ?? '')) !== '' ? $item->name : $item->code;
        $options[$item->id] = $prefix . $label;

        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
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
}
