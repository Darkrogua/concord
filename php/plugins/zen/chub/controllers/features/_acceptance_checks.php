<?php
/**
 * @var \Backend\Widgets\Form $form
 * @var \Zen\Chub\Models\Feature $model
 */

$criteria = [];
if ($model instanceof \Zen\Chub\Models\Feature) {
    $raw = $model->acceptance_criteria;
    if (is_array($raw)) {
        foreach ($raw as $item) {
            if (is_array($item)) {
                $text = trim((string) ($item['value'] ?? ''));
            } else {
                $text = trim((string) $item);
            }
            if ($text !== '') {
                $criteria[] = $text;
            }
        }
    }
}

$checks = $model instanceof \Zen\Chub\Models\Feature && is_array($model->acceptance_checks)
    ? $model->acceptance_checks
    : [];

if ($criteria === []) {
    echo '<p class="text-muted">Добавьте критерии приёмки на вкладке выше, затем отметьте выполненные.</p>';

    return;
}
?>
<div class="feature-ac-checks">
    <p class="help-block">Отметьте выполненные критерии приёмки.</p>
    <?php foreach ($criteria as $index => $text): ?>
        <?php $checked = ! empty($checks[(string) $index]) || ! empty($checks[$index]); ?>
        <div class="checkbox">
            <label>
                <input
                    type="checkbox"
                    name="Feature[acceptance_checks][<?= (int) $index ?>]"
                    value="1"
                    <?= $checked ? 'checked' : '' ?>
                >
                <?= e($text) ?>
            </label>
        </div>
    <?php endforeach ?>
</div>
