<?php

$command = $this->formGetModel();
if (
    !$command
    || !$command->exists
    || (bool) ($command->is_folder ?? false)
    || !(bool) ($command->api_enabled ?? false)
) {
    return;
}

$code = trim((string) ($command->code ?? ''));
if ($code === '') {
    ?>
    <p class="help-block">Укажите код запуска для формирования ссылки.</p>
    <?php
    return;
}

$query = http_build_query(['code' => $code]);
$url = \Url::to('/chub.api/CommandApi:exec') . ($query !== '' ? '?' . $query : '');
?>
<div class="form-group api-exec-link-hint">
    <p class="help-block" style="margin-bottom: 6px;">Вызов по HTTP (GET/POST):</p>
    <p class="form-control-static" style="word-break: break-all;">
        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($url) ?></a>
    </p>
</div>
