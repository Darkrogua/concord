<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Cron;
use Flash;

class Crons extends Controller
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

    public function onSaveData()
    {
        try {
            $crons_data = [];
            foreach (Cron::all() as $cron) {
                $cron_data = $cron->getAttributes();
                $cron_data['planners'] = $cron->planners;
                $crons_data[] = $cron_data;
            }

            $file_path = Files::make()->defineFilePath(
                base_path('plugins/zen/chub/data/crons/crons_data.json')
            );

            Transformers::make()->arrayToFile($crons_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'crons.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/crons/crons_data.json');
            $crons_data = Transformers::make()->arrayFromFile($file_path);

            if (!$crons_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Cron::truncate();

            foreach ($crons_data as $cron_data) {
                if (is_string($cron_data['planners'] ?? null)) {
                    $cron_data['planners'] = Transformers::make()->fromJson($cron_data['planners']) ?? [];
                }

                $cron = new Cron();
                $cron->forceFill($cron_data);
                $cron->save();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'crons.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }
}
