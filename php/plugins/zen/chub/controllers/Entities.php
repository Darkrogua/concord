<?php namespace Zen\Chub\Controllers;

use Db;
use BackendMenu;
use Backend\Classes\Controller;
use Backend\Classes\FormField;
use Flash;
use Illuminate\Support\Str;

use Zen\Chub\Classes\Tools\EntityBuilder;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Models\Entity;
use Zen\Chub\Classes\Support\Transformers;

class Entities extends Controller
{
    private const ENTITY_BUILDER_FIELDS = [
        'build_entity',
        'builder_name',
        'builder_name_plural',
        'builder_name_title',
        'builder_name_create',
        'builder_name_update',
    ];

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

    public function index()
    {
        $this->addJs('/plugins/zen/chub/assets/js/entities-folder-click.js');
        $this->asExtension('ListController')->index();
    }

    public function onSaveData()
    {
        try {
            $entities_data = [];

            foreach (Entity::all() as $entity) {
                $entity_attributes = $entity->getAttributes();
                $entities_data[] = [
                    'id' => $entity_attributes['id'] ?? null,
                    'code' => $entity_attributes['code'] ?? null,
                    'name' => $entity_attributes['name'] ?? null,
                    'sort_order' => $entity_attributes['sort_order'] ?? null,
                    'url' => $entity_attributes['url'] ?? null,
                    'icon_path' => $entity_attributes['icon_path'] ?? null,
                    'parent_id' => $entity_attributes['parent_id'] ?? null,
                    'active' => $entity_attributes['active'] ?? 1,
                    'template_data' => $entity_attributes['template_data'] ?? null,
                ];
            }

            $file_path = $this->getEntitiesJsonPath();
            $dir_path = dirname($file_path);

            if (!is_dir($dir_path)) {
                mkdir($dir_path, 0755, true);
            }

            Transformers::make()->arrayToFile($entities_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'entities.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $entities_data = $this->scan();

            if (!$entities_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'entities.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function scan(): array
    {
        $json_path = $this->getEntitiesJsonPath();
        if (!file_exists($json_path)) {
            throw new \Exception('entities.json не найден по пути: ' . $json_path);
        }

        $entities_data = Transformers::make()->arrayFromFile($json_path);
        if (!is_array($entities_data)) {
            throw new \Exception('Ошибка декодирования entities.json');
        }

        Entity::truncate();

        foreach ($entities_data as $entity) {
            if (!is_array($entity)) {
                continue;
            }

            if (!array_key_exists('active', $entity) || $entity['active'] === null || $entity['active'] === '') {
                $entity['active'] = 1;
            }

            $entity_model = new Entity();
            $entity_model->forceFill($entity);
            $entity_model->save();
        }

        $this->syncEntityIdSequence();

        return $entities_data;
    }

    private function getEntitiesJsonPath(): string
    {
        return base_path('plugins/zen/chub/entities.json');
    }

    private function syncEntityIdSequence(): void
    {
        Db::statement("
            SELECT setval(
                pg_get_serial_sequence('zen_chub_entities', 'id'),
                COALESCE((SELECT MAX(id) FROM zen_chub_entities), 0) + 1,
                false
            )
        ");
    }

    public function listOverrideRecordUrl($record, $definition = null)
    {
        $url = $record->url ?? null;

        if (!$url) {
            return ['clickable' => false];
        }

        return ltrim($url, '/');
    }

    public function listInjectRowClass($record, $definition = null)
    {
        $url = $record->url ?? null;

        if (!$url) {
            return 'list-row-folder';
        }

        return '';
    }

    public function formBeforeSave($model)
    {
        if (!$model instanceof Entity) {
            return;
        }

        $form_widget = $this->formGetWidget();

        foreach (self::ENTITY_BUILDER_FIELDS as $field_name) {
            $form_widget->setSaveDataOverride($field_name, FormField::NO_SAVE_DATA);
            unset($model->{$field_name});
        }
    }

    public function formAfterSave($model)
    {
        if (!$model instanceof Entity) {
            return;
        }

        $post_data = post('Entity', []);
        if (!is_array($post_data)) {
            return;
        }

        $build_entity = (bool) ($post_data['build_entity'] ?? false);
        if (!$build_entity) {
            return;
        }

        $entity_code = trim((string) ($model->code ?? ''));
        $entity_name = trim((string) ($model->name ?? ''));
        $fallback_name = $entity_code !== '' ? $entity_code : $entity_name;

        $builder_name = trim((string) ($post_data['builder_name'] ?? ''));
        if ($builder_name === '') {
            $builder_name = Str::studly($fallback_name);
        }

        $builder_name_plural = trim((string) ($post_data['builder_name_plural'] ?? ''));
        if ($builder_name_plural === '') {
            $builder_name_plural = Str::plural(Str::snake($builder_name));
        }

        $builder_name_title = trim((string) ($post_data['builder_name_title'] ?? 'Записи'));
        $builder_name_create = trim((string) ($post_data['builder_name_create'] ?? 'Создать запись'));
        $builder_name_update = trim((string) ($post_data['builder_name_update'] ?? 'Изменить запись'));

        $template_data_payload = [
            'builder_name' => $builder_name,
            'builder_name_plural' => $builder_name_plural,
            'builder_name_title' => $builder_name_title,
            'builder_name_create' => $builder_name_create,
            'builder_name_update' => $builder_name_update,
        ];

        $model->template_data = json_encode($template_data_payload, JSON_UNESCAPED_UNICODE);
        $model->save();

        EntityBuilder::make()->create(
            $builder_name,
            $builder_name_plural,
            $builder_name_title,
            $builder_name_create,
            $builder_name_update,
        );

        Flash::success('Шаблонный код сущности собран');
    }

    public function onRemoveEntityTemplate()
    {
        try {
            $entity = $this->formGetModel();
            if (!$entity || !$entity->exists) {
                throw new \RuntimeException('Сначала сохраните запись сущности');
            }

            $template_data = json_decode((string) $entity->template_data, true);
            if (!is_array($template_data)) {
                throw new \RuntimeException('Не найдены данные шаблона в template_data');
            }

            $builder_name = trim((string) ($template_data['builder_name'] ?? ''));
            $builder_name_plural = trim((string) ($template_data['builder_name_plural'] ?? ''));

            if ($builder_name === '' || $builder_name_plural === '') {
                throw new \RuntimeException('template_data поврежден: отсутствуют builder_name или builder_name_plural');
            }

            EntityBuilder::make()->remove($builder_name, $builder_name_plural);

            $entity->template_data = null;
            $entity->save();

            Flash::success('Сгенерированная сущность удалена');
        } catch (\Throwable $e) {
            LogsApp::addAdminActionError($e, 'entities.remove_generated');
            Flash::error('Не удалось удалить сгенерированную сущность: ' . $e->getMessage());
        }
    }

}
