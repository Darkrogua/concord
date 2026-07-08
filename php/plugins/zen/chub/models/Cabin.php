<?php namespace Zen\Chub\Models;

use Model;

/**
 * Model
 */
class Cabin extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_cabins';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'deck_id' => 'required|integer|exists:zen_chub_decks,id',
        'grade_id' => 'required|integer|exists:zen_chub_grades,id',
    ];

    public $belongsTo = [
        'deck' => [
            Deck::class,
            'key' => 'deck_id',
        ],
        'grade' => [
            Grade::class,
            'key' => 'grade_id',
        ],
    ];

    public $fillable = [
        'id',
        'name',
        'description',
        'deck_id',
        'grade_id',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Виртуальное поле ship_id: не в БД. shipIdFilter + shipRestrictMode задают фильтр категорий.
     * shipRestrictMode: null — как при первом открытии записи; true — фильтр по выбранному теплоходу; false — «все категории».
     */
    protected ?int $shipIdFilter = null;

    protected ?bool $shipRestrictMode = null;

    protected function afterFetch(): void
    {
        $this->shipIdFilter = null;
        $this->shipRestrictMode = null;
    }

    public function getShipIdAttribute(): ?int
    {
        if ($this->shipRestrictMode === false) {
            return null;
        }

        if ($this->shipRestrictMode === true) {
            return $this->shipIdFilter;
        }

        if (!$this->grade_id) {
            return null;
        }

        return (int) Grade::query()->whereKey($this->grade_id)->value('ship_id');
    }

    public function setShipIdAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->shipIdFilter = null;
            $this->shipRestrictMode = false;

            return;
        }

        $this->shipIdFilter = (int) $value;
        $this->shipRestrictMode = true;
    }

    public function setDeckIdAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['deck_id'] = null;

            return;
        }

        $this->attributes['deck_id'] = Deck::normalizeToRootDeckId((int) $value);
    }

    public function setGradeIdAttribute($value): void
    {
        $this->attributes['grade_id'] = ($value === '' || $value === null) ? null : (int) $value;
    }

    /**
     * Опции палуб для формы (dropdown deck_id).
     */
    public function getDeckIdOptions(): array
    {
        return Deck::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Опции теплоходов (виртуальное поле ship_id).
     */
    public function getShipIdOptions(): array
    {
        return Ship::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getGradeIdOptions($value = null, $fieldName = null, $data = null): array
    {
        $shipId = $this->resolveShipIdForGradeOptions($data);

        $query = Grade::query()->orderBy('name');
        if ($shipId) {
            $query->where('ship_id', $shipId);
        }

        return $query->pluck('name', 'id')->all();
    }

    protected function resolveShipIdForGradeOptions($data): ?int
    {
        if (is_array($data) && array_key_exists('ship_id', $data)) {
            if ($data['ship_id'] === '' || $data['ship_id'] === null) {
                return null;
            }

            return (int) $data['ship_id'];
        }

        if (is_object($data) && property_exists($data, 'ship_id')) {
            if ($data->ship_id === '' || $data->ship_id === null) {
                return null;
            }

            return (int) $data->ship_id;
        }

        if ($this->shipRestrictMode === false) {
            return null;
        }

        if ($this->shipRestrictMode === true && $this->shipIdFilter !== null) {
            return $this->shipIdFilter;
        }

        if ($this->grade_id) {
            return (int) Grade::query()->whereKey($this->grade_id)->value('ship_id');
        }

        return null;
    }
}
