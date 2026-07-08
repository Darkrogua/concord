<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Models\Grade;
use Zen\Chub\Models\Ship;
use Zen\Chub\Classes\Support\Transformers;

/**
 * Model
 */
class GradeAlias extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_grade_aliases';

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

    public $belongsTo = [
        'provider' => [
            'Zen\Chub\Models\Provider',
            'order' => 'sort_order',
            'key' => 'provider_id'
        ],
        'grade' => [
            'Zen\Chub\Models\Grade',
            'order' => 'sort_order',
            'key' => 'target_id'
        ],
    ];

    /**
     * Теплоход через категорию каюты (GradeAlias > Grade > Ship)
     */
    public function ship()
    {
        return $this->hasOneThrough(
            Ship::class,
            Grade::class,
            'ship_id',
            'id',
            'target_id',
            'id'
        );
    }

    /**
     * Создать или обновить алиас по ключу (target_id, provider_id, source_uid).
     *
     * @return static
     */
    public static function addAlias(
        int $target_id,
        int $provider_id,
        string $source_uid,
        ?string $source_name = null,
        array $data = [],
    ): static {
        $values = [
            'source_name' => $source_name,
            'data' => Transformers::make()->toJson($data) ?? [],
        ];

        return self::updateOrCreate(
            [
                'target_id' => $target_id,
                'provider_id' => $provider_id,
                'source_uid' => $source_uid,
            ],
            $values
        );
    }
}
