<?php
/** Partial: подсказка Dom ID в форме блока */
$dom_id = is_object($model ?? null) ? trim((string) ($model->dom_id ?? '')) : '';
$code = is_object($model ?? null) ? trim((string) ($model->code ?? '')) : '';
?>
<?php if ($dom_id !== ''): ?>
<div class="callout callout-info">
    <p><strong>Dom ID:</strong> <code><?= e($dom_id) ?></code></p>
    <p class="help-block">Корневой элемент partial: <code>uuid="<?= e($dom_id) ?>"</code> или <code>uuid="{{ block_dom_id }}"</code>.</p>
    <p class="help-block">Code: <code><?= e($code) ?></code></p>
</div>
<?php endif; ?>
