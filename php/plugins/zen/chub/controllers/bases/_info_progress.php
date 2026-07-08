<?php
$show_records_progress = false;
$records_count = 0;
$records_max = 100;
$records_percent = 0;
$preview_table = 'records';

try {
    $base = $this->formGetModel();

    if ($base && $base->exists) {
        $sqlite = \Zen\Chub\Classes\System\Sqlite::connect($base->getBasePath());
        $preview_table = $this->getPreviewTableForUi();

        if ($sqlite->tableExists($preview_table)) {
            $show_records_progress = true;
            $records_count = (int) $sqlite->query($preview_table)->count();

            $records_max = 100;
            while ($records_count >= $records_max) {
                $records_max *= 10;
            }

            $records_percent = $records_max > 0
                ? min(100, round(($records_count / $records_max) * 100, 2))
                : 0;
        }
    }
} catch (\Throwable $e) {
    $show_records_progress = false;
}
?>

<?php if ($show_records_progress): ?>
<div class="form-group">
    <div>
        <h4>Заполненность таблицы <?= e($preview_table) ?></h4>
        <div class="progress">
            <div
                class="progress-bar"
                role="progressbar"
                style="width: <?= e($records_percent) ?>%;"
                aria-valuenow="<?= e($records_percent) ?>"
                aria-valuemin="0"
                aria-valuemax="100">
                <?= e($records_percent) ?>%
            </div>
        </div>
        <p><?= e($records_count) ?> из <?= e($records_max) ?></p>
    </div>
</div>
<?php endif; ?>
