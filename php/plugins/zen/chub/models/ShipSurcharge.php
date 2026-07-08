<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Models\Ship;

/**
 * Model
 */
class ShipSurcharge extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_ship_surcharge';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'name' => 'required',
    ];

    /**
     * @var array fillable fields.
     */
    public $fillable = [
        'name',
        'description',
        'active',
        'sort_order',
    ];

    /**
     * @var array belongs to many relations.
     */
    public $belongsToMany = [
        'ships' => [
            Ship::class,
            'table' => 'zen_chub_ship_surcharge_pivot',
            'key' => 'surcharge_id',
            'otherKey' => 'ship_id',
            'order' => 'sort_order',
        ],
    ];

}
