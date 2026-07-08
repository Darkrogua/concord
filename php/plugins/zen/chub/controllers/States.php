<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Symfony\Component\Yaml\Yaml;
use Flash;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\State;

class States extends Controller
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
            $states_data = [];
            foreach (State::all() as $state) {
                $states_data[] = $state->getAttributes();
            }

            $file_path = Files::make()->defineFilePath(
                base_path('plugins/zen/chub/data/states/states_data.json')
            );

            Transformers::make()->arrayToFile($states_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'states.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/states/states_data.json');
            $states_data = Transformers::make()->arrayFromFile($file_path);

            if (!$states_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            State::truncate();

            foreach ($states_data as $state_data) {
                $state = new State();
                $state->forceFill($state_data);
                $state->save();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'states.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function formExtendFields($form)
    {
        if (!$form->model instanceof State) {
            return;
        }

        $state = $form->model;
        if (!$state->exists) {
            return;
        }

        $state_code = $this->normalizeStateCode($state->code);
        if ($state_code === '') {
            return;
        }

        $dynamic_fields = $this->loadDynamicFieldsConfig($state_code);
        if (empty($dynamic_fields)) {
            return;
        }

        $state_data = $this->loadStateData($state_code);
        foreach ($dynamic_fields as $field_name => $field_config) {
            if (array_key_exists($field_name, $state_data)) {
                $form->model->setAttribute($field_name, $state_data[$field_name]);
            }
        }

        // addFields() без секции рендерит поля над вкладками (outside).
        $form->addFields($dynamic_fields);
    }

    public function formAfterSave($model)
    {
        if (!$model instanceof State) {
            return;
        }

        $state_code = $this->normalizeStateCode($model->code);
        if ($state_code === '') {
            return;
        }

        $dynamic_fields = $this->loadDynamicFieldsConfig($state_code);
        if (empty($dynamic_fields)) {
            return;
        }

        $post_data = post('State', []);
        if (!is_array($post_data)) {
            return;
        }

        $state_data = [];
        foreach ($dynamic_fields as $field_name => $field_config) {
            if (array_key_exists($field_name, $post_data)) {
                $state_data[$field_name] = $post_data[$field_name];
            }
        }

        $this->storeStateData($state_code, $state_data);
    }

    public function formBeforeSave($model)
    {
        if (!$model instanceof State) {
            return;
        }

        $state_code = $this->normalizeStateCode($model->code);
        if ($state_code === '') {
            return;
        }

        $dynamic_fields = $this->loadDynamicFieldsConfig($state_code);
        if (empty($dynamic_fields)) {
            return;
        }

        // Динамические поля хранятся в JSON (storeStateData), не в zen_chub_states.
        // Раньше использовали setSaveDataOverride(NO_SAVE_DATA), но FormField::NO_SAVE_DATA === -1,
        // а поля type:number приводят значения к float: -1.0 !== -1 в FormModelSaver, и атрибут всё равно уходил в SQL.
        $dynamic_names = array_keys($dynamic_fields);
        $model->bindEventOnce('model.saveInternal', function () use ($model, $dynamic_names) {
            foreach ($dynamic_names as $field_name) {
                unset($model->attributes[$field_name]);
            }
        });
    }

    private function loadDynamicFieldsConfig(string $state_code): array
    {
        $fields_path = base_path('plugins/zen/chub/models/state/fields_' . $state_code . '.yaml');
        if (!file_exists($fields_path)) {
            return [];
        }

        $yaml = Yaml::parseFile($fields_path);
        if (!is_array($yaml)) {
            return [];
        }

        $dynamic_fields = $yaml['fields'] ?? [];
        if (!is_array($dynamic_fields)) {
            return [];
        }

        return $dynamic_fields;
    }

    private function loadStateData(string $state_code): array
    {
        $file_path = $this->getStateDataPath($state_code);
        if (!file_exists($file_path)) {
            return [];
        }

        $content = file_get_contents($file_path);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function storeStateData(string $state_code, array $state_data): void
    {
        $file_path = $this->getStateDataPath($state_code);
        $dir_path = dirname($file_path);

        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        file_put_contents(
            $file_path,
            json_encode($state_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function getStateDataPath(string $state_code): string
    {
        return base_path('plugins/zen/chub/data/states/state_' . $state_code . '.json');
    }

    private function normalizeStateCode(?string $state_code): string
    {
        $state_code = trim((string) $state_code);
        $state_code = preg_replace('/[^a-z0-9_-]/i', '_', $state_code);
        return trim((string) $state_code, '_');
    }
}
