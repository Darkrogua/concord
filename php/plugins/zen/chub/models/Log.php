<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Classes\Enums\LogType;

/**
 * Model
 */
class Log extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $connection = 'chub_logs_sqlite';

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'records';

    const UPDATED_AT = null;

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'type',
        'key',
        'data',
        'created_at',
    ];

    public function getTypeOptions()
    {
        return LogType::options();
    }

    /**
     * Поиск без lower() — SQLite не поддерживает lower() для кириллицы.
     */
    public function scopeSearchLogs($query, $term, $columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($words)) {
            return $query;
        }

        $query->where(function ($q) use ($columns, $words) {
            foreach ($columns as $field) {
                $q->orWhere(function ($sub) use ($field, $words) {
                    foreach ($words as $word) {
                        $sub->where($field, 'LIKE', '%' . $word . '%');
                    }
                });
            }
        });
    }
}
