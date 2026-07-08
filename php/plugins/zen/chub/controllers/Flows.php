<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Flow;

class Flows extends Controller
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

    public function formExtendFields($form): void
    {
        $flow = $form->model ?? null;
        if (!$flow instanceof Flow || !$flow->exists) {
            return;
        }

        if ((bool) ($flow->is_folder ?? false)) {
            $form->removeField('flow_app');
            $form->removeField('process_app');
        }
    }

    public function onSaveData()
    {
        try {
            $flows_data = [];
            foreach (Flow::all() as $flow) {
                $flows_data[] = $flow->getAttributes();
            }

            $file_path = base_path('plugins/zen/chub/data/flows/flows_data.json');
            $dir_path = dirname($file_path);
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0755, true);
            }

            Transformers::make()->arrayToFile($flows_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'flows.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/flows/flows_data.json');
            $flows_data = Transformers::make()->arrayFromFile($file_path);

            if (!$flows_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Flow::truncate();
            $normalized = [];
            foreach ($flows_data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (!array_key_exists('parent_id', $row)) {
                    $row['parent_id'] = null;
                }
                if (!array_key_exists('is_folder', $row)) {
                    $row['is_folder'] = 0;
                }
                $normalized[] = $row;
            }
            Flow::insert($normalized);
            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'flows.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

}
