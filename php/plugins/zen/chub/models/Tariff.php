<?php namespace Zen\Chub\Models;

use Model;

/**
 * Model
 */
class Tariff extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_tariffs';

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
        'created_at',
        'updated_at',
    ];
}
