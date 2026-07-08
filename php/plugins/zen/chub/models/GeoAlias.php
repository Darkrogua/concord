<?php namespace Zen\Chub\Models;

use Model;

class GeoAlias extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'zen_chub_geo_aliases';

    public $rules = [
        'geo_object_id' => 'required|integer',
        'source_code' => 'required',
        'source_id' => 'required',
    ];

    public $fillable = [
        'geo_object_id',
        'source_code',
        'source_id',
        'source_name',
        'active',
    ];

    public $belongsTo = [
        'geoObject' => [
            GeoObject::class,
            'key' => 'geo_object_id',
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}
