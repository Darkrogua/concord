<div class="report-widget widget-checkins-count">
    <h3><?= e($this->property('title')) ?></h3>

    <?php if (!isset($error)): ?>
        <p class="checkins-count-value"><?= number_format((int) $checkins_count, 0, ',', ' ') ?></p>
        <p class="checkins-count-caption text-muted">записей в таблице заездов</p>
        <p class="checkins-count-actions">
            <a href="<?= e($list_url) ?>" class="btn btn-sm btn-default">Открыть список</a>
        </p>
    <?php else: ?>
        <div class="callout callout-danger">
            <div class="content"><?= e($error) ?></div>
        </div>
    <?php endif ?>
</div>
