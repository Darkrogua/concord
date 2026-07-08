<?php namespace Zen\Chub\Models;

use Model;
use Zen\Chub\Classes\System\CommandApp;
use Zen\Chub\Classes\System\Sqlite;

/**
 * Model
 */
class Command extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\SimpleTree;

    /**
     * @var string table in the database used by the model.
     */
    public $table = 'zen_chub_commands';

    /**
     * @var array rules for validation.
     */
    public $rules = [
    ];

    public $fillable = [
        'id',
        'name',
        'code',
        'description',
        'parent_id',
        'is_folder',
        'count_enabled',
        'output_log',
        'api_enabled',
        'active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * Временное хранилище кода команды (хранится в файле, не в БД).
     */
    private ?string $data_code = null;
    private ?array $log_stats_cache = null;

    /** @var array<int, int> кэш folder_id => 0|1 (для списка) */
    private static array $api_enabled_tree_folder_cache = [];

    /**
     * Для списка (колонка api_enabled + valueFrom): у папки = 1, если хотя бы у одной
     * команды в поддереве (is_folder=0) включён api_enabled.
     */
    public function getApiEnabledTreeAttribute(): int
    {
        if (!$this->id) {
            return 0;
        }

        if (!(bool) ($this->is_folder ?? false)) {
            return (int) ((bool) ($this->attributes['api_enabled'] ?? false));
        }

        $folder_id = (int) $this->id;
        if (array_key_exists($folder_id, self::$api_enabled_tree_folder_cache)) {
            return self::$api_enabled_tree_folder_cache[$folder_id];
        }

        $descendant_ids = $this->collectDescendantIds();
        if ($descendant_ids === []) {
            self::$api_enabled_tree_folder_cache[$folder_id] = 0;
            return 0;
        }

        $on = self::query()
            ->whereIn('id', $descendant_ids)
            ->where('is_folder', 0)
            ->where('api_enabled', 1)
            ->exists();

        $value = $on ? 1 : 0;
        self::$api_enabled_tree_folder_cache[$folder_id] = $value;

        return $value;
    }

    /**
     * Путь к файлу с кодом команды.
     */
    public function getDataFilePath(): string
    {
        return base_path('plugins/zen/chub/data/commands/command_' . $this->id . '.php');
    }

    /**
     * Возвращает код команды из файлового хранилища.
     */
    public function getDataAttribute($value = null): string
    {
        if ($this->id) {
            $file_path = $this->getDataFilePath();
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                return $content === false ? '' : $content;
            }
        }

        return $this->data_code ?? '';
    }

    /**
     * Сохраняет код во временный буфер до afterSave.
     */
    public function setDataAttribute($value): void
    {
        $this->data_code = is_string($value) ? $value : '';
        $this->attributes['data'] = $this->data_code;
    }

    /**
     * Убираем виртуальное поле data перед SQL-сохранением.
     */
    public function beforeSave(): void
    {
        if ((bool) ($this->output_log ?? false)) {
            $this->count_enabled = 1;
            $this->attributes['count_enabled'] = 1;
        }

        unset($this->attributes['data']);
    }

    /**
     * Записывает код команды в файл plugins/zen/chub/data/commands/command_{id}.php
     */
    public function afterSave(): void
    {
        $this->syncDescendantsActiveState();
        $this->clearCommandsIndexCache();
        $this->syncLogBase();

        if ($this->data_code === null || !$this->id) {
            return;
        }

        $file_path = $this->getDataFilePath();
        $dir_path = dirname($file_path);

        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        file_put_contents($file_path, $this->data_code);
    }

    /**
     * Удаляет файл команды после удаления записи.
     */
    public function afterDelete(): void
    {
        $this->clearCommandsIndexCache();
        $this->removeLogBase();

        if (!$this->id) {
            return;
        }

        $file_path = base_path('plugins/zen/chub/data/commands/command_' . $this->id . '.php');
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }

    /**
     * Сброс индекса code -> command_file для CommandApp.
     */
    private function clearCommandsIndexCache(): void
    {
        CommandApp::clearCache();
    }

    /**
     * Путь к sqlite-базе логов запуска команды.
     */
    public function getLogDbPath(): string
    {
        return base_path('storage/chub/bases/command_log_' . $this->id . '.sqlite');
    }

    /**
     * Количество вызовов команды для списка.
     * Показывается только для is_folder=0 и count_enabled=1.
     */
    public function getCallsCountAttribute($value = null): ?int
    {
        $log_stats = $this->getLogStats();
        if (!(bool) ($log_stats['enabled'] ?? false)) {
            return null;
        }

        return (int) ($log_stats['count'] ?? 0);
    }

    /**
     * Время последнего вызова команды для списка.
     * Показывается только для is_folder=0 и count_enabled=1.
     */
    public function getLastCallAtAttribute($value = null): ?string
    {
        $log_stats = $this->getLogStats();
        if (!(bool) ($log_stats['enabled'] ?? false)) {
            return null;
        }

        $last_call_at = $log_stats['last_call_at'] ?? null;
        return is_string($last_call_at) && $last_call_at !== '' ? $last_call_at : null;
    }

    /**
     * Создать/удалить базу логов в зависимости от флага count_enabled.
     */
    private function syncLogBase(): void
    {
        if (!$this->id) {
            return;
        }

        if ((bool) $this->count_enabled) {
            $this->ensureLogBase();
            return;
        }

        $this->removeLogBase();
    }

    /**
     * Убедиться, что база логов и таблица records существуют.
     */
    private function ensureLogBase(): void
    {
        $db_path = $this->getLogDbPath();
        $dir_path = dirname($db_path);

        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }

        $sqlite = file_exists($db_path)
            ? Sqlite::connect($db_path)
            : Sqlite::create($db_path);

        if (!$sqlite->tableExists('records')) {
            $sqlite->createTable('records', function ($table) {
                $table->id();
                $table->text('data')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Удалить базу логов, если она существует.
     */
    private function removeLogBase(): void
    {
        if (!$this->id) {
            return;
        }

        $db_path = $this->getLogDbPath();
        if (!file_exists($db_path)) {
            return;
        }

        Sqlite::connect($db_path)->drop();
    }

    /**
     * Ленивая статистика вызовов по sqlite-логу команды.
     */
    private function getLogStats(): array
    {
        if ($this->log_stats_cache !== null) {
            return $this->log_stats_cache;
        }

        $enabled = $this->id
            && !(bool) ($this->is_folder ?? false)
            && (bool) ($this->count_enabled ?? false);

        if (!$enabled) {
            $this->log_stats_cache = [
                'enabled' => false,
                'count' => null,
                'last_call_at' => null,
            ];
            return $this->log_stats_cache;
        }

        $db_path = $this->getLogDbPath();
        if (!file_exists($db_path)) {
            $this->log_stats_cache = [
                'enabled' => true,
                'count' => 0,
                'last_call_at' => null,
            ];
            return $this->log_stats_cache;
        }

        try {
            $sqlite = Sqlite::connect($db_path);
            if (!$sqlite->tableExists('records')) {
                $this->log_stats_cache = [
                    'enabled' => true,
                    'count' => 0,
                    'last_call_at' => null,
                ];
                return $this->log_stats_cache;
            }

            $count = (int) $sqlite->query('records')->count();
            $last_call_at = $sqlite->query('records')
                ->orderBy('id', 'desc')
                ->value('created_at');

            $this->log_stats_cache = [
                'enabled' => true,
                'count' => $count,
                'last_call_at' => is_string($last_call_at) ? $last_call_at : null,
            ];
            return $this->log_stats_cache;
        } catch (\Throwable $e) {
            $this->log_stats_cache = [
                'enabled' => true,
                'count' => 0,
                'last_call_at' => null,
            ];
            return $this->log_stats_cache;
        }
    }

    /**
     * Синхронизирует active у всех потомков:
     * - если текущий элемент выключен, выключаем всех потомков;
     * - если текущий элемент это папка и включен, включаем всех потомков.
     */
    private function syncDescendantsActiveState(): void
    {
        if (!$this->id) {
            return;
        }

        $active_value = (int) ((bool) $this->active);
        $should_propagate = false;

        if ($active_value === 0) {
            $should_propagate = true;
        } elseif ((bool) $this->is_folder && $active_value === 1) {
            $should_propagate = true;
        }

        if (!$should_propagate) {
            return;
        }

        $descendant_ids = $this->collectDescendantIds();
        if (empty($descendant_ids)) {
            return;
        }

        self::query()
            ->whereIn('id', $descendant_ids)
            ->update([
                'active' => $active_value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Собирает id всех потомков через обход дерева по parent_id.
     */
    private function collectDescendantIds(): array
    {
        $descendant_ids = [];
        $visited = [];
        $queue = [$this->id];

        while (!empty($queue)) {
            $parent_id = array_shift($queue);

            if (isset($visited[$parent_id])) {
                continue;
            }
            $visited[$parent_id] = true;

            $children_ids = self::query()
                ->where('parent_id', $parent_id)
                ->pluck('id')
                ->all();

            foreach ($children_ids as $child_id) {
                $child_id = (int) $child_id;
                if ($child_id <= 0) {
                    continue;
                }

                if (!in_array($child_id, $descendant_ids, true)) {
                    $descendant_ids[] = $child_id;
                    $queue[] = $child_id;
                }
            }
        }

        return $descendant_ids;
    }

    /**
     * Устанавливает parent_id, преобразуя пустую строку в null.
     */
    public function setParentIdAttribute($value)
    {
        $this->attributes['parent_id'] = ($value === '' || $value === null) ? null : (int) $value;
    }

    /**
     * Получает опции для выбора родителя.
     */
    public function getParentIdOptions(): array
    {
        // Получаем все элементы, исключая текущий
        $query = self::orderBy('name');
        if ($this->id) {
            $query->where('id', '<>', $this->id);
        }
        $items = $query->get();
        
        $options = ['' => '-- Верхний уровень --'];
        
        // Получаем все корневые элементы (без родителя)
        $root_items = $items->whereNull('parent_id');
        
        // Рекурсивно строим иерархию с отступами
        foreach ($root_items as $item) {
            $this->buildParentOptions($item, $items, $options, 0);
        }
        
        return $options;
    }

    /**
     * Рекурсивно строит опции для выбора родителя с отступами.
     */
    private function buildParentOptions($item, $all_items, &$options, int $depth): void
    {
        // Проверяем, не является ли элемент потомком текущего элемента
        if ($this->id) {
            $current = $item;
            while ($current && $current->parent_id) {
                if ($current->parent_id == $this->id) {
                    return; // Это потомок текущего элемента, пропускаем
                }
                $current = $all_items->where('id', $current->parent_id)->first();
                if (!$current) {
                    break;
                }
            }
        }
        
        $prefix = str_repeat('— ', $depth);
        $options[$item->id] = $prefix . $item->name;
        
        // Рекурсивно обрабатываем детей
        $children = $all_items->where('parent_id', $item->id)->sortBy('name');
        foreach ($children as $child) {
            $this->buildParentOptions($child, $all_items, $options, $depth + 1);
        }
    }
}
