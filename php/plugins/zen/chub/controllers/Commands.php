<?php namespace Zen\Chub\Controllers;

use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use Backend\Widgets\Lists;
use Db;
use Flash;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\System\CommandApp;
use Zen\Chub\Classes\System\LogsApp;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Models\BaseRecord;
use Zen\Chub\Models\Command;

class Commands extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    private bool $command_log_list_initialized = false;
    private ?Lists $command_log_list_widget = null;
    private ?string $command_log_list_message = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Zen.Chub', 'chub');
        $this->initCommandLogListWidget();
    }

    public function onRunCommandPopup()
    {
        $this->vars['command_popup_title'] = 'Результат выполнения';
        $this->vars['command_popup_content'] = 'Команда не выполнена';

        $command = $this->formGetModel();
        if (!$command || !$command->exists) {
            $this->vars['command_popup_content'] = 'Сначала сохраните команду.';
            return $this->makePartial('run_result_popup');
        }

        if ((bool) $command->is_folder) {
            $this->vars['command_popup_content'] = 'Папка не может быть выполнена как команда.';
            return $this->makePartial('run_result_popup');
        }

        if (!(bool) $command->active) {
            $this->vars['command_popup_content'] = 'Команда неактивна.';
            return $this->makePartial('run_result_popup');
        }

        try {
            // Выполняется файл plugins/zen/chub/data/commands/command_{id}.php (include):
            // CommandApp::execModel → execCommandMeta → CommandApp::execFile.
            // Возвращаемое значение include попадает в formatCommandResult() и показывается в попапе.
            $result = CommandApp::execModel($command);
            $this->vars['command_popup_content'] = $this->formatCommandResult($result);
        } catch (\Throwable $e) {
            $this->vars['command_popup_content'] = 'Ошибка выполнения: ' . $e->getMessage();
        }

        return $this->makePartial('run_result_popup');
    }

    public function onRefreshCommandRunFragments(): array
    {
        return [
            '#command-run-fragment' => $this->makePartial('run_button_inner', $this->getRunButtonVars()),
            '#command-log-fragment' => $this->makePartial('log_tab_inner'),
        ];
    }

    public function onSaveData()
    {
        try {
            $commands_data = [];
            foreach (Command::all() as $command) {
                $command_data = $command->getAttributes();
                $command_data['data'] = $command->data;
                $commands_data[] = $command_data;
            }

            $file_path = base_path('plugins/zen/chub/data/commands/commands_data.json');
            $dir_path = dirname($file_path);
            if (!file_exists($dir_path)) {
                mkdir($dir_path, 0755, true);
            }

            Transformers::make()->arrayToFile($commands_data, $file_path);
            Flash::success('Данные сохранены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'commands.save');
            Flash::error('Ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    public function onRestoreData()
    {
        try {
            $file_path = base_path('plugins/zen/chub/data/commands/commands_data.json');
            $commands_data = Transformers::make()->arrayFromFile($file_path);

            if (!$commands_data) {
                Flash::warning('Файл данных пустой или не найден');
                return $this->listRefresh();
            }

            Command::truncate();

            foreach ($commands_data as $command_data) {
                $command = new Command();
                $command->forceFill($command_data);
                $command->save();
            }

            $this->syncCommandIdSequence();
            Flash::success('Данные восстановлены');
        } catch (\Exception $e) {
            LogsApp::addAdminActionError($e, 'commands.restore');
            Flash::error('Ошибка при восстановлении данных: ' . $e->getMessage());
        }

        return $this->listRefresh();
    }

    private function syncCommandIdSequence(): void
    {
        Db::statement("
            SELECT setval(
                pg_get_serial_sequence('zen_chub_commands', 'id'),
                COALESCE((SELECT MAX(id) FROM zen_chub_commands), 0) + 1,
                false
            )
        ");
    }

    public function hasCommandLogListWidget(): bool
    {
        return $this->initCommandLogListWidget();
    }

    public function getCommandLogListWidget(): ?Lists
    {
        $this->initCommandLogListWidget();
        return $this->command_log_list_widget;
    }

    public function getCommandLogListMessage(): ?string
    {
        $this->initCommandLogListWidget();
        return $this->command_log_list_message;
    }

    private function formatCommandResult(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($result === null) {
            return 'Команда выполнена, но ничего не вернула (null).';
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if (is_scalar($result)) {
            return (string) $result;
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: 'Команда выполнена.';
    }

    private function getRunButtonVars(): array
    {
        $command = $this->resolveCurrentCommand();
        $show_calls_count = $command
            && $command->exists
            && !(bool) ($command->is_folder ?? false)
            && (bool) ($command->count_enabled ?? false);

        $calls_count = 0;
        if ($show_calls_count) {
            $calls_count = CommandApp::getCallsCountByCommandId((int) $command->id);
        }

        return [
            'show_calls_count' => $show_calls_count,
            'calls_count' => $calls_count,
        ];
    }

    private function initCommandLogListWidget(): bool
    {
        if ($this->command_log_list_initialized) {
            return $this->command_log_list_widget !== null;
        }

        $this->command_log_list_initialized = true;

        try {
            $command = $this->resolveCurrentCommand();
            if (!$command || !$command->exists) {
                $this->command_log_list_message = 'Сначала сохраните команду.';
                return false;
            }

            if (!(bool) $command->count_enabled) {
                $this->command_log_list_message = 'Логирование выключено (count_enabled=0).';
                return false;
            }

            $db_path = $command->getLogDbPath();
            if (!file_exists($db_path)) {
                $this->command_log_list_message = 'База логов ещё не создана.';
                return false;
            }

            $sqlite = Sqlite::connect($db_path);
            if (!$sqlite->tableExists('records')) {
                $this->command_log_list_message = 'Таблица logs.records не обнаружена.';
                return false;
            }

            $records_model = new BaseRecord();
            $records_model->setConnection($sqlite->getConnectionName());
            $records_model->setTable('records');

            $list_config = $this->makeConfig([
                'alias' => 'commandLogList',
                'model' => $records_model,
                'columns' => [
                    'id' => [
                        'label' => 'ID',
                        'type' => 'number',
                        'sortable' => true,
                    ],
                    'created_at' => [
                        'label' => 'Запуск',
                        'type' => 'datetime',
                        'format' => 'd.m.Y H:i:s',
                        'sortable' => true,
                    ],
                    'data' => [
                        'label' => 'Данные',
                        'type' => 'text',
                        'sortable' => false,
                    ],
                ],
                'recordsPerPage' => 20,
                'showPagination' => 'auto',
                'showPageNumbers' => true,
                'showSorting' => true,
                'defaultSort' => [
                    'column' => 'id',
                    'direction' => 'desc',
                ],
                'customPageName' => 'command_log_page',
                'noRecordsMessage' => 'Запусков пока нет',
            ]);

            $this->command_log_list_widget = $this->makeWidget(Lists::class, $list_config);
            $this->command_log_list_widget->bindToController();

            return true;
        } catch (\Throwable $e) {
            $this->command_log_list_widget = null;
            $this->command_log_list_message = 'Не удалось загрузить лог: ' . $e->getMessage();
            return false;
        }
    }

    private function resolveCurrentCommand(): ?Command
    {
        try {
            $command = $this->formGetModel();
            if ($command instanceof Command && $command->exists) {
                return $command;
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

        return Command::find($record_id);
    }

}
