<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\CronApp;

/**
 * Model
 */
class Cron extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_crons';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    /**
     * @var array fillable fields.
     */
    public $fillable = [
        'id',
        'name',
        'description',
        'planners',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public function getPlannersAttribute(?string $value = null): array
    {
        return Transformers::make()->fromJson($value) ?? [];
    }

    public function setPlannersAttribute(?array $planners): void
    {
        $this->attributes['planners'] = $planners
            ? Transformers::make()->toJson($planners)
            : null;
    }

    public function afterSave()
    {
        CronApp::make()->clearCronCache();
    }

}
