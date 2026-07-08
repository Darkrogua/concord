<?php namespace Zen\Act\Models;

use Illuminate\Support\Str;
use Model;
use RainLab\User\Models\User;
use Zen\Act\Classes\Support\ActStorage;
use Zen\Act\Classes\System\AccessApp;
use Zen\Act\Classes\System\ActApp;

/**
 * Акт — контракт/сценарий событий (основные поля в БД, снимки в storage/acts).
 */
class Act extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $incrementing = false;

    protected $keyType = 'string';

    public $table = 'zen_act_acts';

    protected $dates = [
        'activate_at',
        'stop_at',
    ];

    public $rules = [
        'name' => 'required|max:255',
        'owner_id' => 'nullable|integer|exists:users,id',
    ];

    public $fillable = [
        'id',
        'name',
        'description',
        'owner_id',
        'activate_at',
        'stop_at',
        'created_at',
        'updated_at',
    ];

    public $belongsTo = [
        'owner' => [User::class, 'key' => 'owner_id'],
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });

        static::saved(function (self $model): void {
            if ($model->wasRecentlyCreated) {
                $owner = $model->owner;
                if ($owner) {
                    AccessApp::make()->bootstrap((string) $model->id, (string) ($owner->username ?? ''));
                }
                ActStorage::make()->writeInitialState($model);
            }

            ActApp::make()->syncCurrentFile((string) $model->id);
        });

        static::deleted(function (self $model): void {
            ActStorage::make()->removeActDirectory((string) $model->id);
            AccessApp::make()->removeViewerIndex((string) $model->id);
        });
    }
}
