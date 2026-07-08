<?php namespace Zen\Chub\Models;

use Db;
use Model;

/**
 * Model
 */
class Deck extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_decks';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'name',
        'description',
        'active',
        'sort_order',
        'parent_id',
        'root_deck_id',
        'created_at',
        'updated_at',
    ];

    public function beforeValidate()
    {
        $this->syncRootDeckIdFromParent();
    }

    public function afterCreate()
    {
        if (!$this->parent_id) {
            Db::table($this->table)->where('id', $this->id)->update(['root_deck_id' => $this->id]);
            $this->root_deck_id = (int) $this->id;
        }
    }

    public function afterSave()
    {
        if ($this->wasRecentlyCreated) {
            return;
        }

        if ($this->wasDirty('parent_id')) {
            static::rebuildRootDeckIdsColumn();
        }
    }

    /**
     * Опции фильтра списка кают: только корневые палубы.
     */
    public function scopeFilterRootsOnly($query)
    {
        return $query->whereNull($this->getParentColumnName());
    }

    /**
     * id корневой палубы для произвольной палубы (по колонке root_deck_id или обходом вверх).
     */
    public static function normalizeToRootDeckId(?int $deckId): ?int
    {
        if (!$deckId) {
            return null;
        }

        $rootId = static::query()->whereKey($deckId)->value('root_deck_id');
        if ($rootId !== null) {
            return (int) $rootId;
        }

        $deck = static::find($deckId);
        if (!$deck) {
            return null;
        }

        return $deck->computeRootIdFromRow();
    }

    public static function rebuildRootDeckIdsColumn(): void
    {
        $rows = static::query()->orderBy('id')->get(['id', 'parent_id']);
        $byId = $rows->keyBy('id');

        foreach ($rows as $row) {
            $rootId = static::computeRootIdForRow((int) $row->id, $byId);
            static::query()->whereKey($row->id)->update(['root_deck_id' => $rootId]);
        }
    }

    protected function syncRootDeckIdFromParent(): void
    {
        if ($this->parent_id) {
            $parent = static::find($this->parent_id);
            $this->root_deck_id = $parent
                ? (int) ($parent->root_deck_id ?: $parent->id)
                : null;

            return;
        }

        $this->root_deck_id = $this->exists ? (int) $this->id : null;
    }

    protected function computeRootIdFromRow(): int
    {
        $id = (int) $this->id;
        $parentId = $this->parent_id ? (int) $this->parent_id : null;
        $guard = 0;
        while ($parentId && $guard++ < 1000) {
            $row = static::query()->whereKey($parentId)->first(['id', 'parent_id']);
            if (!$row) {
                break;
            }
            $id = (int) $row->id;
            $parentId = $row->parent_id ? (int) $row->parent_id : null;
        }

        return $id;
    }

    /**
     * @param \Illuminate\Support\Collection $byId keyed by id
     */
    protected static function computeRootIdForRow(int $deckId, $byId): int
    {
        $current = $byId->get($deckId);
        if (!$current) {
            return $deckId;
        }

        $guard = 0;
        while ($current->parent_id && $guard++ < 1000) {
            $parent = $byId->get((int) $current->parent_id);
            if (!$parent) {
                break;
            }
            $current = $parent;
        }

        return (int) $current->id;
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
}
