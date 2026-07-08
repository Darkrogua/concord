<?php
/**
 * @var \Zen\Chub\Models\Feature $model
 */

use Backend;
use Zen\Chub\Classes\System\FeatureApp;

if (! $model instanceof \Zen\Chub\Models\Feature || ! $model->exists) {
    echo '<p class="text-muted">Сохраните фичу, чтобы увидеть обратные зависимости.</p>';

    return;
}

$items = FeatureApp::make()->getDependedBy((string) $model->id);

if ($items === []) {
    echo '<p class="text-muted">Ни одна фича не ссылается на эту запись.</p>';

    return;
}
?>
<div class="feature-depended-by">
    <p class="help-block">Фичи, которые зависят от текущей (обратные ссылки по графу).</p>
    <ul class="list-unstyled">
        <?php foreach ($items as $item): ?>
            <li>
                <a href="<?= Backend::url('zen/chub/features/update/'.$item['id']) ?>">
                    <?= e($item['name']) ?>
                </a>
                <?php if (! empty($item['feature_group'])): ?>
                    <span class="text-muted"> · <?= e($item['feature_group']) ?></span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
</div>
