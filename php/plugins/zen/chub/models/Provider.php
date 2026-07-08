<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;

/**
 * Model
 */
class Provider extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_providers';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'name',
        'code',
        'description',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public static function legacyToCurrent(string $eds_code): int
    {
        # Переименование
        if ($eds_code === 'waterway') {
            $eds_code = 'vodohod';
        }

        $eds = Transformers::make()->arrayFromFile(
            storage_path('chub/providers.json')
        );

        if ($eds) {
            return $eds[$eds_code];
        }

        $providers = self::get();

        $eds = [];
        foreach ($providers as $provider) {
            $eds[$provider->code] = $provider->id;
        }

        Transformers::make()->arrayToFile(
            $eds,
            Files::make()->defineFilePath(
                storage_path('chub/providers.json')
            )
        );

        return $eds[$eds_code];
    }

    public function afterSave()
    {
        if (file_exists(storage_path('chub/providers.json'))) {
            unlink(storage_path('chub/providers.json'));
        }
    }
}
