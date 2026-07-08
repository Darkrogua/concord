<?php namespace Zen\Chub\Models;

use Db;
use Model;
use Zen\Chub\Models\ShipAmenity;
use Zen\Chub\Models\ShipClass;
use Zen\Chub\Models\ShipInclusion;
use Zen\Chub\Models\ShipSpecification;
use Zen\Chub\Models\ShipSurcharge;

/**
 * Model
 */
class Ship extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;


    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_ships';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'name' => 'required',
        'ship_class_id' => 'required',
    ];

    /**
     * @var array fillable fields.
     */
    public $fillable = [
        'name',
        'description',
        'ship_class_id',
        'meta_title',
        'meta_description',
        'active',
        'sort_order',
    ];

    /**
     * @var array attach many fields.
     */
    public $attachMany = [
        'images' => [
            'System\Models\File',
            'order' => 'sort_order',
            'delete' => true
        ],
    ];

    /**
     * @var array belongs to relations.
     */
    public $belongsTo = [
        'shipClass' => ShipClass::class,
    ];

    /**
     * Временное хранилище данных из repeater (в стоимость входит)
     */
    protected ?array $inclusions_repeater_data = null;

    /**
     * Временное хранилище данных из repeater (удобства)
     */
    protected ?array $amenities_repeater_data = null;

    /**
     * Временное хранилище данных из repeater (доплаты)
     */
    protected ?array $surcharges_repeater_data = null;

    /**
     * Временное хранилище данных из repeater (технические характеристики)
     */
    protected ?array $specifications_repeater_data = null;

    /**
     * @var array belongs to many relations.
     */
    public $belongsToMany = [
        'inclusions' => [
            ShipInclusion::class,
            'table' => 'zen_chub_ship_inclusion_pivot',
            'key' => 'ship_id',
            'otherKey' => 'inclusion_id',
            'pivot' => ['sort_order'],
        ],
        'amenities' => [
            ShipAmenity::class,
            'table' => 'zen_chub_ship_amenity_pivot',
            'key' => 'ship_id',
            'otherKey' => 'amenity_id',
            'pivot' => ['sort_order'],
        ],
        'surcharges' => [
            ShipSurcharge::class,
            'table' => 'zen_chub_ship_surcharge_pivot',
            'key' => 'ship_id',
            'otherKey' => 'surcharge_id',
            'pivot' => ['sort_order'],
        ],
        'specifications' => [
            ShipSpecification::class,
            'table' => 'zen_chub_ship_specification_pivot',
            'key' => 'ship_id',
            'otherKey' => 'specification_id',
            'pivot' => ['value', 'sort_order'],
        ],
    ];

    /**
     * Options для dropdown внутри repeater.
     */
    /**
     * Опции для выбора класса корабля (form: options: getShipClassOptions).
     */
    public function getShipClassOptions(): array
    {
        return ShipClass::orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    public function getInclusionIdOptions(): array
    {
        return ShipInclusion::orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Options для dropdown внутри repeater.
     */
    public function getAmenityIdOptions(): array
    {
        return ShipAmenity::orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Options для dropdown внутри repeater.
     */
    public function getSurchargeIdOptions(): array
    {
        return ShipSurcharge::orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Options для dropdown внутри repeater.
     */
    public function getSpecificationIdOptions(): array
    {
        return ShipSpecification::orderBy('sort_order')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Значение для repeater (формируется из pivot таблицы с учетом sort_order).
     */
    public function getInclusionsRepeaterAttribute(): array
    {
        if (!$this->exists) {
            return [];
        }

        $ids = Db::table('zen_chub_ship_inclusion_pivot')
            ->where('ship_id', $this->id)
            ->orderBy('sort_order')
            ->pluck('inclusion_id')
            ->all();

        return array_map(fn($id) => ['inclusion_id' => (int) $id], $ids);
    }

    /**
     * Значение для repeater (формируется из pivot таблицы с учетом sort_order).
     */
    public function getAmenitiesRepeaterAttribute(): array
    {
        if (!$this->exists) {
            return [];
        }

        $ids = Db::table('zen_chub_ship_amenity_pivot')
            ->where('ship_id', $this->id)
            ->orderBy('sort_order')
            ->pluck('amenity_id')
            ->all();

        return array_map(fn($id) => ['amenity_id' => (int) $id], $ids);
    }

    /**
     * Значение для repeater (формируется из pivot таблицы с учетом sort_order).
     */
    public function getSurchargesRepeaterAttribute(): array
    {
        if (!$this->exists) {
            return [];
        }

        $ids = Db::table('zen_chub_ship_surcharge_pivot')
            ->where('ship_id', $this->id)
            ->orderBy('sort_order')
            ->pluck('surcharge_id')
            ->all();

        return array_map(fn($id) => ['surcharge_id' => (int) $id], $ids);
    }

    /**
     * Значение для repeater (формируется из pivot таблицы с учетом sort_order).
     */
    public function getSpecificationsRepeaterAttribute(): array
    {
        if (!$this->exists) {
            return [];
        }

        return Db::table('zen_chub_ship_specification_pivot')
            ->where('ship_id', $this->id)
            ->orderBy('sort_order')
            ->get(['specification_id', 'value'])
            ->map(function ($row) {
                return [
                    'specification_id' => (int) $row->specification_id,
                    'value' => (string) ($row->value ?? ''),
                ];
            })
            ->all();
    }

    /**
     * Сохраняем пришедший из формы repeater в память, чтобы синхронизировать pivot после save.
     */
    public function setInclusionsRepeaterAttribute($value): void
    {
        $this->inclusions_repeater_data = is_array($value) ? $value : [];
    }

    /**
     * Сохраняем пришедший из формы repeater в память, чтобы синхронизировать pivot после save.
     */
    public function setAmenitiesRepeaterAttribute($value): void
    {
        $this->amenities_repeater_data = is_array($value) ? $value : [];
    }

    /**
     * Сохраняем пришедший из формы repeater в память, чтобы синхронизировать pivot после save.
     */
    public function setSurchargesRepeaterAttribute($value): void
    {
        $this->surcharges_repeater_data = is_array($value) ? $value : [];
    }

    /**
     * Сохраняем пришедший из формы repeater в память, чтобы синхронизировать pivot после save.
     */
    public function setSpecificationsRepeaterAttribute($value): void
    {
        $this->specifications_repeater_data = is_array($value) ? $value : [];
    }

    public function beforeSave(): void
    {
        // Не даем October попытаться сохранить виртуальное поле в БД
        if (array_key_exists('inclusions_repeater', $this->attributes)) {
            $this->inclusions_repeater_data = $this->attributes['inclusions_repeater'];
            unset($this->attributes['inclusions_repeater']);
        }

        if (array_key_exists('amenities_repeater', $this->attributes)) {
            $this->amenities_repeater_data = $this->attributes['amenities_repeater'];
            unset($this->attributes['amenities_repeater']);
        }

        if (array_key_exists('surcharges_repeater', $this->attributes)) {
            $this->surcharges_repeater_data = $this->attributes['surcharges_repeater'];
            unset($this->attributes['surcharges_repeater']);
        }

        if (array_key_exists('specifications_repeater', $this->attributes)) {
            $this->specifications_repeater_data = $this->attributes['specifications_repeater'];
            unset($this->attributes['specifications_repeater']);
        }
    }

    public function afterSave(): void
    {
        if ($this->inclusions_repeater_data !== null) {
            $sync = [];
            $i = 0;
            foreach ($this->inclusions_repeater_data as $row) {
                $inclusion_id = $row['inclusion_id'] ?? null;
                if (!$inclusion_id) {
                    continue;
                }
                $sync[(int) $inclusion_id] = ['sort_order' => $i++];
            }

            // Синхронизируем pivot (и порядок)
            $this->inclusions()->sync($sync);

            // Сбрасываем буфер
            $this->inclusions_repeater_data = null;
        }

        if ($this->amenities_repeater_data !== null) {
            $sync = [];
            $i = 0;
            foreach ($this->amenities_repeater_data as $row) {
                $amenity_id = $row['amenity_id'] ?? null;
                if (!$amenity_id) {
                    continue;
                }
                $sync[(int) $amenity_id] = ['sort_order' => $i++];
            }

            // Синхронизируем pivot (и порядок)
            $this->amenities()->sync($sync);

            // Сбрасываем буфер
            $this->amenities_repeater_data = null;
        }

        if ($this->surcharges_repeater_data !== null) {
            $sync = [];
            $i = 0;
            foreach ($this->surcharges_repeater_data as $row) {
                $surcharge_id = $row['surcharge_id'] ?? null;
                if (!$surcharge_id) {
                    continue;
                }
                $sync[(int) $surcharge_id] = ['sort_order' => $i++];
            }

            // Синхронизируем pivot (и порядок)
            $this->surcharges()->sync($sync);

            // Сбрасываем буфер
            $this->surcharges_repeater_data = null;
        }

        if ($this->specifications_repeater_data !== null) {
            $sync = [];
            $i = 0;
            foreach ($this->specifications_repeater_data as $row) {
                $specification_id = $row['specification_id'] ?? null;
                if (!$specification_id) {
                    continue;
                }

                $sync[(int) $specification_id] = [
                    'value' => (string) ($row['value'] ?? ''),
                    'sort_order' => $i++,
                ];
            }

            // Синхронизируем pivot (значение и порядок)
            $this->specifications()->sync($sync);

            // Сбрасываем буфер
            $this->specifications_repeater_data = null;
        }
    }

}
