<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Zen\Chub\Classes\System\FeatureApp;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Feature;

class Features extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';

    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
    }

    public function listExtendQuery($query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function formExtendFields($form): void
    {
        $feature = $form->model ?? null;
        if (! $feature instanceof Feature) {
            return;
        }

        $options = $this->buildFeatureDropdownOptions($feature->id ? (string) $feature->id : null);

        if ($form->getField('dependencies_rows')) {
            $nested = $form->getField('dependencies_rows')->config->form ?? null;
            if ($nested && $nested->getField('depends_on_feature_id')) {
                $nested->getField('depends_on_feature_id')->options = $options;
            }
        }

        $feature->setAttribute(
            'acceptance_criteria',
            $this->listToRepeaterRows($feature->acceptance_criteria)
        );

        if ($feature->exists) {
            $feature->setAttribute('dependencies_rows', $feature->getDependenciesFormRows());
        }
    }

    public function formBeforeSave($model): void
    {
        if (! $model instanceof Feature) {
            return;
        }

        $model->acceptance_criteria = $this->repeaterValuesToList(post('Feature.acceptance_criteria'));

        $checks = post('Feature.acceptance_checks');
        $model->acceptance_checks = is_array($checks) ? $checks : [];

        $dep_rows = post('Feature.dependencies_rows');
        $payload = [];
        if (is_array($dep_rows)) {
            foreach ($dep_rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $payload[] = [
                    'depends_on_feature_id' => (string) ($row['depends_on_feature_id'] ?? ''),
                    'comment' => (string) ($row['comment'] ?? ''),
                ];
            }
        }
        $model->setDependenciesSyncPayload($payload);
    }

    public function onSaveData()
    {
        try {
            FeatureApp::make()->saveToDataFile();
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'features.save');
            Flash::error('Ошибка при сохранении данных: '.$e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $count = FeatureApp::make()->restoreFromDataFile();
            if ($count === 0) {
                Flash::warning('Файл данных пустой или не найден');

                return $this->listRefresh();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'features.restore');
            Flash::error('Ошибка при восстановлении данных: '.$e->getMessage());
        }

        return $this->listRefresh();
    }

    /**
     * @return array<string, string>
     */
    private function buildFeatureDropdownOptions(?string $exclude_id): array
    {
        $query = Feature::orderBy('name');
        if ($exclude_id) {
            $query->where('id', '<>', $exclude_id);
        }

        $options = ['' => '-- Выберите фичу --'];
        foreach ($query->get() as $item) {
            $label = trim((string) ($item->name ?? ''));
            if ($item->feature_group) {
                $label .= ' · '.$item->feature_group;
            }
            $options[(string) $item->id] = $label !== '' ? $label : (string) $item->id;
        }

        return $options;
    }

    /**
     * @param mixed $value
     * @return array<int, array{value: string}>
     */
    private function listToRepeaterRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (is_array($item) && array_key_exists('value', $item)) {
                $text = trim((string) $item['value']);
            } else {
                $text = trim((string) $item);
            }
            if ($text !== '') {
                $rows[] = ['value' => $text];
            }
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     * @return array<int, string>
     */
    private function repeaterValuesToList(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $list = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '') {
                $list[] = $value;
            }
        }

        return Feature::normalizeStringList($list);
    }
}
