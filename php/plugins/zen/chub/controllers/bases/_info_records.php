<div class="form-group">
    <div>
        <?php $preview_table = $this->getPreviewTableForUi(); ?>
        <h4>Записи таблицы <?= e($preview_table) ?></h4>
        <?php if ($this->hasRecordsListWidget()): ?>
            <?= $this->getRecordsToolbarWidget()->render() ?>
            <?= $this->getRecordsListWidget()->render() ?>
        <?php else: ?>
            <p><?= e($this->getRecordsListMessage() ?? 'Список таблицы недоступен.') ?></p>
        <?php endif; ?>
    </div>
</div>
