<?php

use Zen\Chub\Classes\System\BlockApp;

/**
 * Таб «Представления»: embed-превью блока в iframe.
 *
 * @var \Backend\Widgets\Form $form
 */

$block = $this->formGetModel();
if (
    !$block
    || !$block->exists
    || (bool) ($block->is_folder ?? false)
) {
    return;
}

$code = trim((string) ($block->code ?? ''));
if ($code === '') {
    ?>
    <p class="help-block">Укажите код блока и сохраните запись, чтобы увидеть превью.</p>
    <?php

    return;
}

$app = BlockApp::make();
$variants = $app->listPresentationVariants($code);
if ($variants === []) {
    ?>
    <p class="help-block">Варианты представления для блока <code><?= e($code) ?></code> не найдены.</p>
    <?php

    return;
}

    $demo_scenarios = ($app->showByCode($code)['demo_scenarios'] ?? ['default']);
$first_variant = $variants[0];
$first_width = max(200, min(2560, (int) ($first_variant['width'] ?? 1280)));
$first_url = $app->previewUrl($code, (string) ($first_variant['code'] ?? 'default'), $first_width);
$store_book_url = $app->storeBookViewUrl($code);
?>
<div class="block-preview-tab">
    <p class="help-block" style="margin-bottom: 12px;">
        Превью по сохранённым файлам на диске. Ширина iframe = viewport варианта.
        <a href="<?= e($store_book_url) ?>" target="_blank" rel="noopener noreferrer">Store Book</a>
    </p>

    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 12px;">
        <label for="block-preview-variant-select" style="margin: 0;">Вариант:</label>
        <select id="block-preview-variant-select" class="form-control" style="width: auto; min-width: 220px;">
            <?php foreach ($variants as $variant): ?>
                <?php
                $variant_code = (string) ($variant['code'] ?? 'default');
                $scene_width = max(200, min(2560, (int) ($variant['width'] ?? 1280)));
                $preview_url = $app->previewUrl($code, $variant_code, $scene_width);
                $label = (string) ($variant['label'] ?? $variant_code);
                ?>
                <option
                    value="<?= e($preview_url) ?>"
                    data-width="<?= $scene_width ?>"
                >
                    <?= e($label) ?> (<?= e($variant_code) ?>, <?= $scene_width ?>px)
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (count($demo_scenarios) > 1): ?>
            <label for="block-preview-demo-select" style="margin: 0;">Demo:</label>
            <select id="block-preview-demo-select" class="form-control" style="width: auto; min-width: 160px;">
                <?php foreach ($demo_scenarios as $demo_id): ?>
                    <option value="<?= e((string) $demo_id) ?>"><?= e((string) $demo_id) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <button type="button" class="btn btn-default btn-sm" id="block-preview-reload">Обновить</button>
        <a class="btn btn-default btn-sm" id="block-preview-open" href="<?= e($first_url) ?>" target="_blank" rel="noopener noreferrer">Открыть</a>
    </div>

    <div style="overflow-x: auto; max-width: 100%; padding-bottom: 4px;">
        <iframe
            id="block-preview-frame"
            src="<?= e($first_url) ?>"
            title="Превью блока"
            width="<?= $first_width ?>"
            height="640"
            style="width: <?= $first_width ?>px; max-width: none; height: 640px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; display: block;"
        ></iframe>
    </div>
</div>

<script>
(() => {
    const variantSelect = document.getElementById('block-preview-variant-select');
    const demoSelect = document.getElementById('block-preview-demo-select');
    const frame = document.getElementById('block-preview-frame');
    const openLink = document.getElementById('block-preview-open');
    const reloadBtn = document.getElementById('block-preview-reload');

    if (!variantSelect || !frame) {
        return;
    }

    const currentUrl = () => {
        const base = variantSelect.value;
        if (!demoSelect) {
            return base;
        }

        const url = new URL(base, window.location.origin);
        url.searchParams.set('demo', demoSelect.value);

        return url.pathname + url.search;
    };

    const applyPreview = () => {
        const option = variantSelect.selectedOptions[0];
        const width = parseInt(option?.dataset.width || '1280', 10) || 1280;
        const url = currentUrl();

        frame.width = width;
        frame.style.width = width + 'px';
        frame.src = url;

        if (openLink) {
            openLink.href = url;
        }
    };

    variantSelect.addEventListener('change', applyPreview);

    if (demoSelect) {
        demoSelect.addEventListener('change', applyPreview);
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', () => {
            frame.contentWindow?.location.reload();
        });
    }
})();
</script>
