<?php if ($this->hasCommandLogListWidget()): ?>
    <div class="layout-row">
        <?= $this->getCommandLogListWidget()->render() ?>
    </div>
<?php else: ?>
    <p class="text-muted" style="margin: 10px 0;">
        <?= e($this->getCommandLogListMessage() ?: 'Лог недоступен') ?>
    </p>
<?php endif; ?>
