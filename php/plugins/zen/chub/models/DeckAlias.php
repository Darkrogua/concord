<?php namespace Zen\Chub\Models;

use Model;

/**
 * Model
 */
class DeckAlias extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_deck_aliases';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'description',
        'provider_id',
        'target_id',
        'source_name',
        'source_uid',
        'data',
        'active',
        'created_at',
        'updated_at',
    ];
}
