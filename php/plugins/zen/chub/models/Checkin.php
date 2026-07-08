<?php namespace Zen\Chub\Models;

use Db;
use Model;
use October\Rain\Exception\ValidationException;
use Zen\Chub\Classes\Enums\CheckinPointType;

/**
 * Model
 */
class Checkin extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_checkins';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'ship_id' => 'required|integer',
        'date_start' => 'required',
        'date_end' => 'required',
    ];

    public $fillable = [
        'provider_id',
        'source_id',
        'ship_id',
        'date_start',
        'date_end',
        'description',
        'active',
    ];

    protected ?array $route_points_repeater_data = null;

    public $belongsTo = [
        'ship' => [
            Ship::class,
            'key' => 'ship_id',
        ],
    ];

    public $hasMany = [
        'routePoints' => [
            CheckinRoutePoint::class,
            'key' => 'checkin_id',
            'order' => 'sort_order',
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function getShipIdOptions(): array
    {
        return Ship::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getGeoObjectIdOptions(): array
    {
        return GeoObject::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getPointTypeOptions(): array
    {
        return CheckinPointType::options();
    }

    public function getRoutePointsRepeaterAttribute(): array
    {
        if (!$this->exists) {
            return [];
        }

        return Db::table('zen_chub_checkin_route_points')
            ->where('checkin_id', $this->id)
            ->orderBy('sort_order')
            ->get([
                'geo_object_id',
                'point_type',
                'arrival_at',
                'departure_at',
                'is_highlight',
                'note',
            ])
            ->map(function ($row) {
                return [
                    'geo_object_id' => (int) $row->geo_object_id,
                    'point_type' => (string) ($row->point_type ?? CheckinPointType::TRANSIT->value),
                    'arrival_at' => $row->arrival_at,
                    'departure_at' => $row->departure_at,
                    'is_highlight' => (int) ($row->is_highlight ?? 0),
                    'note' => (string) ($row->note ?? ''),
                ];
            })
            ->all();
    }

    public function setRoutePointsRepeaterAttribute($value): void
    {
        $this->route_points_repeater_data = is_array($value) ? $value : [];
    }

    public function beforeSave(): void
    {
        if (array_key_exists('route_points_repeater', $this->attributes)) {
            $this->route_points_repeater_data = $this->attributes['route_points_repeater'];
            unset($this->attributes['route_points_repeater']);
        }

        if ($this->date_start && $this->date_end) {
            $start = strtotime((string) $this->date_start);
            $end = strtotime((string) $this->date_end);
            if ($start !== false && $end !== false && $end < $start) {
                throw new ValidationException([
                    'date_end' => 'Дата окончания не может быть раньше даты начала.',
                ]);
            }
        }
    }

    public function afterSave(): void
    {
        if ($this->route_points_repeater_data === null) {
            return;
        }

        Db::table('zen_chub_checkin_route_points')
            ->where('checkin_id', $this->id)
            ->delete();

        $insert_rows = [];
        $sort_order = 0;

        foreach ($this->route_points_repeater_data as $row) {
            $geo_object_id = intval($row['geo_object_id'] ?? 0);
            if ($geo_object_id <= 0) {
                continue;
            }

            $point_type = trim((string) ($row['point_type'] ?? CheckinPointType::TRANSIT->value));
            if (!CheckinPointType::tryFrom($point_type)) {
                $point_type = CheckinPointType::TRANSIT->value;
            }

            $insert_rows[] = [
                'checkin_id' => (int) $this->id,
                'geo_object_id' => $geo_object_id,
                'point_type' => $point_type,
                'arrival_at' => $row['arrival_at'] ?: null,
                'departure_at' => $row['departure_at'] ?: null,
                'is_highlight' => !empty($row['is_highlight']) ? 1 : 0,
                'note' => isset($row['note']) ? trim((string) $row['note']) : null,
                'sort_order' => $sort_order++,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($insert_rows) {
            Db::table('zen_chub_checkin_route_points')->insert($insert_rows);
        }

        $this->route_points_repeater_data = null;
    }
}
