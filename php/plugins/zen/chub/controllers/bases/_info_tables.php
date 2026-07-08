<?php
$tables_stats = [];

try {
    $base = $this->formGetModel();

    if ($base && $base->exists) {
        $sqlite = \Zen\Chub\Classes\System\Sqlite::connect($base->getBasePath());
        $tables_list = $sqlite->listTables();
        $preview_table = $this->getPreviewTableForUi();

        foreach ($tables_list as $table_name) {
            if ($table_name === $preview_table) {
                continue;
            }

            $tables_stats[] = [
                'name' => $table_name,
                'count' => (int) $sqlite->query($table_name)->count(),
            ];
        }
    }
} catch (\Throwable $e) {
    $tables_stats = [];
}
?>

<?php if (!empty($tables_stats)): ?>
<div class="form-group">
    <div>
        <h4>Таблицы базы</h4>
        <p>Список таблиц (кроме выбранной для интерактива) и количество записей.</p>
        <table class="table data">
            <thead>
                <tr>
                    <th>Таблица</th>
                    <th class="text-right">Записей</th>
                    <th class="text-right">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables_stats as $table_stat): ?>
                <tr>
                    <td><?= e($table_stat['name']) ?></td>
                    <td class="text-right"><?= e($table_stat['count']) ?></td>
                    <td class="text-right">
                        <button
                            class="btn btn-xs btn-danger oc-icon-trash-o"
                            data-request="onClearBaseTable"
                            data-request-data="table_name: '<?= e($table_stat['name']) ?>'"
                            data-request-confirm="Очистить таблицу <?= e($table_stat['name']) ?>?"
                            data-request-flash
                            data-stripe-load-indicator>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
