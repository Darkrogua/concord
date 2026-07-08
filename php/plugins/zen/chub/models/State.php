<?php namespace Zen\Chub\Models;

use Model;

/**
 * Model
 */
class State extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;


    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_states';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'code',
        'parent_id',
        'description',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];
}
