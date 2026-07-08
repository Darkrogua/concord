<?php namespace Zen\Chub\Models;

use Model;

class CheckinRoutePoint extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'zen_chub_checkin_route_points';

    public $rules = [
        'checkin_id' => 'required|integer',
        'geo_object_id' => 'required|integer',
        'point_type' => 'required',
    ];

    public $fillable = [
        'checkin_id',
        'geo_object_id',
        'point_type',
        'arrival_at',
        'departure_at',
        'is_highlight',
        'note',
        'sort_order',
    ];

    public $belongsTo = [
        'checkin' => [
            Checkin::class,
            'key' => 'checkin_id',
        ],
        'geoObject' => [
            GeoObject::class,
            'key' => 'geo_object_id',
        ],
    ];
}
