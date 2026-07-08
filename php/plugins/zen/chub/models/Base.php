<?php namespace Zen\Chub\Models;

use Model;

use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Classes\System\Files;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Model
 */
class Base extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_bases';

    /**
     * @var array rules for validation.
     */
    public $rules = [
        'code' => 'required',
    ];

    public $fillable = [
        'id',
        'code',
        'name',
        'description',
        'schema',
        'preview_table',
        'active',
        'sort_order',
        'created_at',
        'updated_at'
    ];

    public function getPreviewTableAttribute(?string $value = null): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : 'records';
    }

    /**
     * Опции таблиц для поля preview_table в форме
     * @return array<string,string>
     */
    public function getPreviewTableOptions(): array
    {
        $options = [
            'records' => 'records',
        ];

        try {
            $db_path = $this->getBasePath();
            if (!file_exists($db_path)) {
                return $options;
            }

            $sqlite = Sqlite::connect($db_path);
            foreach ($sqlite->listTables() as $table_name) {
                $table_name = trim((string) $table_name);
                if ($table_name === '') {
                    continue;
                }

                $options[$table_name] = $table_name;
            }
        } catch (\Throwable $exception) {
        }

        return $options;
    }

    public function getBasePath(): string
    {
        return Files::make()->defineFilePath(
            base_path('storage/chub/bases') . '/' . $this->code . '.sqlite'
        );
    }

    public function beforeCreate(): void
    {
        $this->applyShema();
    }

    public function applyShema()
    {
        $db_path = $this->getBasePath();
        if (!file_exists($db_path)) {
            $sqlite = Sqlite::create($db_path);
            $sqlite->applySchema($this->schema);
        }
    }

    public function getSchemaAttribute(?string $schema = null): string
    {
        if (!$schema) {
            return file_get_contents(
                base_path(
                    'plugins/zen/chub/models/base/default_scheme.php'
                )
            );
        }
        return $schema;
    }

    public function addRecord(array $data): int
    {
        $sqlite = Sqlite::connect($this->getBasePath());
        return $sqlite->query('records')->insertGetId($data);
    }

    public function sqliteQuery(string $table = 'records'): QueryBuilder
    {
        $sqlite = Sqlite::connect($this->getBasePath());
        return $sqlite->query($table);
    }

    public function clearRecords(): void
    {
        $this->clearTable('records');
    }

    public function clearTable(string $table_name): void
    {
        $sqlite = Sqlite::connect($this->getBasePath());

        if (!$sqlite->tableExists($table_name)) {
            return;
        }

        $sqlite->query($table_name)->delete();
    }

    /**
     * Удалить файл базы данных
     */
    public function removeBase()
    {
        $db_path = $this->getBasePath();

        if (!file_exists($db_path)) {
            return;
        }

        Sqlite::connect($db_path)->drop();
    }

    # Пересоздать базу
    public function clearBase()
    {
        $this->removeBase();
        $this->applyShema();
    }

    //public function 

    public function afterDelete()
    {
        $this->removeBase();
    }
}
