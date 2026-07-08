<?php
$field_name = $field->getName();
$field_id = $field->getId();
$props = $field->value ?? [];
if (! is_array($props)) {
    $props = [];
}

$props_json = json_encode($props, JSON_UNESCAPED_UNICODE);
if ($props_json === false) {
    $props_json = '[]';
}
?>

<div
    class="document-props-editor"
    data-document-props-editor
    data-field-id="<?= e($field_id) ?>">
    <textarea
        id="<?= e($field_id) ?>"
        class="document-props-json-sink"
        name="<?= e($field_name) ?>"
        style="display: none"><?= e($props_json) ?></textarea>
    <div class="document-props-rows" data-document-props-rows></div>
    <button type="button" class="document-props-add" data-document-props-add>
        + Добавить свойство
    </button>
</div>
