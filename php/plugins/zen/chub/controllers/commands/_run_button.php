<?php
$command = $this->formGetModel();
$show_calls_count = $command
    && $command->exists
    && !(bool) ($command->is_folder ?? false)
    && (bool) ($command->count_enabled ?? false);

$calls_count = 0;
if ($show_calls_count) {
    $calls_count = \Zen\Chub\Classes\System\CommandApp::getCallsCountByCommandId((int) $command->id);
}
?>

<div id="command-run-fragment" class="form-buttons">
    <?= $this->makePartial('run_button_inner', [
        'show_calls_count' => $show_calls_count,
        'calls_count' => $calls_count,
    ]) ?>
</div>
