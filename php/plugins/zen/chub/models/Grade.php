<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Models\Ship;
use Zen\Chub\Classes\Enums\Tiering;

/**
 * Model
 */
class Grade extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_grades';

    protected $casts = [
        'tiering' => Tiering::class,
    ];

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $belongsTo = [
        'ship' => [
            Ship::class,
            'key' => 'ship_id',
        ],
    ];

    public $fillable = [
        'id',
        'name',
        'description',
        'ship_id',
        'places_main_qnt',
        'places_extra_qnt',
        'rooms_count',
        'area_meters',
        'tiering',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];
}
