<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Models\Ship;

/**
 * Model
 */
class ShipInclusion extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_inclusions';

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
            'table' => 'zen_chub_ship_inclusion_pivot',
            'key' => 'inclusion_id',
            'otherKey' => 'ship_id',
            'order' => 'sort_order',
        ],
    ];

}
