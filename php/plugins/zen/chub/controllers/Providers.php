<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;

use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Provider;

class Providers extends Controller
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
            $providers_data = [];
            foreach (Provider::all() as $provider) {
                $providers_data[] = $provider->getAttributes();
            }

            $file_path = base_path('plugins/zen/chub/data/providers/providers_data.json');
            $dir_path = dirname($file_path);
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0755, true);
            }

            Transformers::make()->arrayToFile($providers_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'providers.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/providers/providers_data.json');
            $providers_data = Transformers::make()->arrayFromFile($file_path);

            if (!$providers_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Provider::truncate();

            foreach ($providers_data as $provider_data) {
                $provider = new Provider();
                $provider->forceFill($provider_data);
                $provider->save();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'providers.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

}
