<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

class Logs extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
    }

    public function onCleanDb()
    {
        try {
            $migration_file = plugins_path('zen/chub/updates/builder_table_create_zen_chub_logs.php');
            require_once $migration_file;

            $migration_class = 'Zen\\Chub\\Updates\\BuilderTableCreateZenChubLogs';
            $migration = new $migration_class();

            try {
                $migration->down();
            } catch (\Throwable $e) {
            }

            $migration->up();

            Flash::success('База логов очищена и пересоздана');
        } catch (\Throwable $e) {
            Flash::error('Ошибка при очистке логов: ' . $e->getMessage());
        }

        return array_merge($this->listRefresh(), [
            '#logs-db-size-info' => $this->makePartial('db_size_info'),
        ]);
    }
}
