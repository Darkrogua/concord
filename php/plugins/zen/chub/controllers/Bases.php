<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Backend\Widgets\Form;
use Backend\Widgets\Lists;
use Backend\Widgets\Toolbar;
use Flash;
use ApplicationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Models\Base;
use Zen\Chub\Models\BaseRecord;

class Bases extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    private bool $records_list_initialized = false;
    private ?Lists $records_list_widget = null;
    private ?Toolbar $records_toolbar_widget = null;
    private ?string $records_list_message = null;
    private ?string $preview_table_override = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
        $this->initRecordsListWidget();
    }

    public function hasRecordsListWidget(): bool
    {
        return $this->initRecordsListWidget();
    }

    public function getRecordsListWidget(): ?Lists
    {
        $this->initRecordsListWidget();
        return $this->records_list_widget;
    }

    public function getRecordsToolbarWidget(): ?Toolbar
    {
        $this->initRecordsListWidget();
        return $this->records_toolbar_widget;
    }

    public function getRecordsListMessage(): ?string
    {
        $this->initRecordsListWidget();
        return $this->records_list_message;
    }

    public function onSaveData()
    {
        try {
            $bases_data = [];
            foreach (Base::all() as $base) {
                $bases_data[] = $base->getAttributes();
            }

            $file_path = base_path('plugins/zen/chub/data/bases/bases_data.json');
            $dir_path = dirname($file_path);
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0755, true);
            }

            Transformers::make()->arrayToFile($bases_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'bases.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/bases/bases_data.json');
            $bases_data = Transformers::make()->arrayFromFile($file_path);

            if (!$bases_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Base::truncate();

            foreach ($bases_data as $base_data) {
                $base = new Base();
                $base->forceFill($base_data);
                $base->save();
            }

            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'bases.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onClearBase()
    {
        try {
            $base = $this->formGetModel();

            if (!$base || !$base->exists) {
                Flash::warning('Сначала сохраните запись базы');
                return;
            }

            $base->clearBase();
            Flash::success('База пересоздана');

            return [
                '#base-info-progress' => $this->makePartial('info_progress'),
                '#base-info-records' => $this->makePartial('info_records'),
                '#base-info-tables' => $this->makePartial('info_tables'),
            ];
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'bases.recreate');
            Flash::error('Ошибка при пересоздании базы: ' . $e->getMessage());
        }
    }

    public function onRefreshBaseInfoProgress()
    {
        return [
            '#base-info-progress' => $this->makePartial('info_progress')
        ];
    }

    public function onPreviewTableChanged()
    {
        $this->preview_table_override = $this->extractPreviewTableFromPost();
        $this->resetRecordsListState();

        return [
            '#base-info-progress' => $this->makePartial('info_progress'),
            '#base-info-records' => $this->makePartial('info_records'),
            '#base-info-tables' => $this->makePartial('info_tables'),
        ];
    }

    public function onClearRecordsTable()
    {
        try {
            $base = $this->formGetModel();
            $preview_table = $base ? $this->resolvePreviewTableName($base) : null;

            if (!$base || !$base->exists) {
                Flash::warning('Сначала сохраните запись базы');
                return;
            }

            if ($preview_table === null) {
                Flash::warning('Не удалось определить таблицу для очистки');
                return;
            }

            $base->clearTable($preview_table);
            Flash::success('Таблица очищена: ' . $preview_table);

            return [
                '#base-info-progress' => $this->makePartial('info_progress'),
                '#base-info-records' => $this->makePartial('info_records'),
                '#base-info-tables' => $this->makePartial('info_tables'),
            ];
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'bases.clear_table');
            Flash::error('Ошибка при очистке таблицы: ' . $e->getMessage());
        }
    }

    public function onClearBaseTable()
    {
        try {
            $base = $this->formGetModel();
            $table_name = trim((string) post('table_name'));

            if (!$base || !$base->exists) {
                Flash::warning('Сначала сохраните запись базы');
                return;
            }

            if ($table_name === '') {
                Flash::warning('Не указано имя таблицы');
                return;
            }

            $base->clearTable($table_name);
            Flash::success('Таблица очищена: ' . $table_name);

            return [
                '#base-info-progress' => $this->makePartial('info_progress'),
                '#base-info-records' => $this->makePartial('info_records'),
                '#base-info-tables' => $this->makePartial('info_tables'),
            ];
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'bases.clear_table');
            Flash::error('Ошибка при очистке таблицы: ' . $e->getMessage());
        }
    }

    private function initRecordsListWidget(): bool
    {
        if ($this->records_list_initialized) {
            return $this->records_list_widget !== null;
        }

        $this->records_list_initialized = true;

        try {
            $base = $this->resolveCurrentBase();
            if (!$base || !$base->exists) {
                $this->records_list_message = 'Сначала сохраните запись базы.';
                return false;
            }

            $sqlite = Sqlite::connect($base->getBasePath());
            $preview_table = $this->resolvePreviewTableName($base);
            if ($preview_table === null || !$sqlite->tableExists($preview_table)) {
                $selected_table = trim((string) ($base->preview_table ?? ''));
                $shown_table = $selected_table !== '' ? $selected_table : 'records';
                $this->records_list_message = 'Таблица не обнаружена: ' . $shown_table . '. Выберите другую таблицу в поле "Таблица для интерактива".';
                return false;
            }

            $records_model = new BaseRecord();
            $records_model->setConnection($sqlite->getConnectionName());
            $records_model->setTable($preview_table);

            $has_id_column = in_array('id', $sqlite->listFields($preview_table), true);

            $list_config = $this->makeConfig([
                'alias' => 'recordsList',
                'model' => $records_model,
                'columns' => $this->resolveRecordsColumnsConfig($base, $sqlite, $preview_table),
                'recordsPerPage' => 20,
                'showPagination' => 'auto',
                'showPageNumbers' => true,
                'showSorting' => true,
                'customPageName' => 'records_page',
                'noRecordsMessage' => 'Таблица ' . $preview_table . ' пуста',
                'recordOnClick' => "$('<a />').popup({ handler: 'onLoadRecordPopup', size: 'huge', extraData: { record_rowid: ':_rowid' } })",
            ]);

            $this->records_list_widget = $this->makeWidget(Lists::class, $list_config);
            $this->records_list_widget->bindEvent('list.extendQuery', function ($query) {
                $query->addSelect(DB::raw('rowid as _rowid'));
            });

            if (!$has_id_column) {
                $this->records_list_widget->bindEvent('list.extendSortColumn', function ($query, $sort_column, $sort_direction) {
                    if ($sort_column === 'id') {
                        $query->reorder(DB::raw('rowid'), $sort_direction);
                    }
                });
            }

            $this->records_list_widget->bindToController();

            $toolbar_config = $this->makeConfig([
                'alias' => 'recordsListToolbar',
                'search' => [
                    'prompt' => 'Поиск по таблице ' . $preview_table,
                ],
            ]);

            $this->records_toolbar_widget = $this->makeWidget(Toolbar::class, $toolbar_config);
            $this->records_toolbar_widget->listWidgetId = $this->records_list_widget->getId();
            $this->records_toolbar_widget->cssClasses[] = 'list-header';

            if ($search_widget = $this->records_toolbar_widget->getSearchWidget()) {
                $search_widget->bindEvent('search.submit', function () use ($search_widget) {
                    $this->records_list_widget->setSearchTerm($search_widget->getActiveTerm(), true);
                    return $this->records_list_widget->onRefresh();
                });

                $this->records_list_widget->setSearchOptions([
                    'mode' => $search_widget->mode,
                    'scope' => $search_widget->scope,
                ]);

                $this->records_list_widget->setSearchTerm($search_widget->getActiveTerm());
            }

            $this->records_toolbar_widget->bindToController();

            return true;
        } catch (\Throwable $e) {
            $this->records_list_widget = null;
            $this->records_toolbar_widget = null;
            $this->records_list_message = 'Не удалось подготовить список таблицы: ' . $e->getMessage();
            return false;
        }
    }

    private function resolveCurrentBase(): ?Base
    {
        try {
            $base = $this->formGetModel();
            if ($base instanceof Base && $base->exists) {
                return $base;
            }
        } catch (\Throwable $e) {
        }

        $record_id = post('id')
            ?: (request()->route('id') ?? null)
            ?: ($this->params[0] ?? null);

        if (!$record_id) {
            $path = trim((string) request()->path(), '/');
            if (preg_match('/\/update\/([^\/]+)$/', $path, $matches)) {
                $record_id = $matches[1];
            }
        }

        if (!$record_id) {
            return null;
        }

        return Base::find($record_id);
    }

    private function resolveRecordsColumnsConfig(Base $base, Sqlite $sqlite, string $table_name): array
    {
        $fields_meta = $sqlite->listFields($table_name, true);
        $db_fields = [];
        foreach ($fields_meta as $field_meta) {
            $db_fields[] = $field_meta['name'];
        }

        $columns_paths = [
            base_path('plugins/zen/chub/models/base/columns_' . $base->code . '_' . $table_name . '.yaml'),
            base_path('plugins/zen/chub/models/base/columns_' . $base->code . '.yaml'),
        ];

        foreach ($columns_paths as $columns_path) {
            if (file_exists($columns_path)) {
                $columns_config = Yaml::parseFile($columns_path);
                $configured_columns = $columns_config['columns'] ?? [];
                $filtered_columns = [];

                foreach ($configured_columns as $column_name => $column_config) {
                    if (!in_array($column_name, $db_fields, true)) {
                        continue;
                    }

                    $filtered_columns[$column_name] = $column_config;
                }

                if (!empty($filtered_columns)) {
                    return $filtered_columns;
                }
            }
        }

        $fallback_columns = [];
        foreach ($fields_meta as $field_meta) {
            $column_name = $field_meta['name'];
            $field_type = strtolower((string) ($field_meta['type'] ?? ''));
            $is_number = str_contains($field_type, 'int')
                || str_contains($field_type, 'real')
                || str_contains($field_type, 'float')
                || str_contains($field_type, 'double')
                || str_contains($field_type, 'numeric');

            $fallback_columns[$column_name] = [
                'label' => $column_name,
                'type' => $is_number ? 'number' : 'text',
                'searchable' => true,
                'sortable' => true,
            ];
        }

        return $fallback_columns;
    }

    public function onLoadRecordPopup()
    {
        $base = $this->resolveCurrentBase();
        if (!$base || !$base->exists) {
            throw new ApplicationException('Сначала сохраните запись базы.');
        }

        $record_rowid = trim((string) post('record_rowid'));
        if ($record_rowid === '') {
            throw new ApplicationException('Не передан идентификатор записи (rowid).');
        }

        $sqlite = Sqlite::connect($base->getBasePath());
        $preview_table = $this->resolvePreviewTableName($base);
        if ($preview_table === null || !$sqlite->tableExists($preview_table)) {
            throw new ApplicationException('Таблица для интерактива не обнаружена.');
        }

        $records_model = new BaseRecord();
        $records_model->setConnection($sqlite->getConnectionName());
        $records_model->setTable($preview_table);

        $record = $records_model->newQuery()
            ->select($preview_table . '.*')
            ->addSelect(DB::raw('rowid as _rowid'))
            ->whereRaw('rowid = ?', [$record_rowid])
            ->first();
        if (!$record) {
            throw new ApplicationException('Запись не найдена: ' . $record_rowid);
        }

        $this->vars['record_form_widget'] = $this->makeRecordFormWidget($base, $record);
        $this->vars['record_popup_title'] = 'Запись ' . $preview_table . ' #' . $record_rowid;

        return $this->makePartial('record_popup');
    }

    private function makeRecordFormWidget(Base $base, BaseRecord $record): Form
    {
        $form_config = $this->makeConfig([
            'alias' => 'recordsPopupForm',
            'model' => $record,
            'fields' => $this->resolveRecordFieldsConfig($base, $record),
            'previewMode' => true,
        ]);

        $form_widget = $this->makeWidget(Form::class, $form_config);
        $form_widget->bindToController();

        return $form_widget;
    }

    private function resolveRecordFieldsConfig(Base $base, BaseRecord $record): array
    {
        $table_name = trim((string) $record->getTable());
        $fields_paths = [
            base_path('plugins/zen/chub/models/base/fields_' . $base->code . '_' . $table_name . '.yaml'),
            base_path('plugins/zen/chub/models/base/fields_' . $base->code . '.yaml'),
        ];

        foreach ($fields_paths as $fields_path) {
            if (file_exists($fields_path)) {
                $fields_config = Yaml::parseFile($fields_path);
                $fields = $fields_config['fields'] ?? [];
                if (!empty($fields)) {
                    return $fields;
                }
            }
        }

        $fields = [];
        foreach ($record->getAttributes() as $field_name => $field_value) {
            if ($field_name === '_rowid') {
                continue;
            }

            $value_text = is_scalar($field_value) || $field_value === null
                ? (string) $field_value
                : json_encode($field_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $is_json_like = is_string($value_text)
                && (str_starts_with(ltrim($value_text), '{') || str_starts_with(ltrim($value_text), '['));

            if ($field_name === 'data' || $is_json_like) {
                $fields[$field_name] = [
                    'label' => $field_name,
                    'span' => 'full',
                    'size' => 'giant',
                    'language' => 'javascript',
                    'type' => 'codeeditor',
                ];
                continue;
            }

            $fields[$field_name] = [
                'label' => $field_name,
                'span' => 'full',
                'type' => 'textarea',
                'size' => 'small',
            ];
        }

        return $fields;
    }

    public function getPreviewTableForUi(): string
    {
        $base = $this->resolveCurrentBase();
        if (!$base || !$base->exists) {
            return 'records';
        }

        $table_name = $this->resolvePreviewTableName($base);

        return $table_name ?? 'records';
    }

    private function resolvePreviewTableName(Base $base): ?string
    {
        $selected_table = $this->preview_table_override;
        if ($selected_table === null) {
            $selected_table = trim((string) ($base->preview_table ?? ''));
        }
        $preferred_tables = [];

        if ($selected_table !== '') {
            $preferred_tables[] = $selected_table;
        }
        $preferred_tables[] = 'records';

        try {
            $sqlite = Sqlite::connect($base->getBasePath());

            foreach ($preferred_tables as $table_name) {
                if ($table_name !== '' && $sqlite->tableExists($table_name)) {
                    return $table_name;
                }
            }

            $tables = $sqlite->listTables();

            return !empty($tables) ? (string) $tables[0] : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resetRecordsListState(): void
    {
        $this->records_list_initialized = false;
        $this->records_list_widget = null;
        $this->records_toolbar_widget = null;
        $this->records_list_message = null;
    }

    private function extractPreviewTableFromPost(): ?string
    {
        $preview_table = post('preview_table');
        if (is_string($preview_table)) {
            $preview_table = trim($preview_table);
            return $preview_table !== '' ? $preview_table : null;
        }

        $base_payload = post('Base');
        if (is_array($base_payload) && isset($base_payload['preview_table'])) {
            $candidate = trim((string) $base_payload['preview_table']);
            return $candidate !== '' ? $candidate : null;
        }

        return null;
    }

}
