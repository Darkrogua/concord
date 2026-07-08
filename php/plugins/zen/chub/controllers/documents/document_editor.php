<?php
$field_name = $field->getName();
$field_id = $field->getId();
$field_value = (string) ($field->value ?? '');
?>

<div
    data-documents-markdown-editor
    data-field-id="<?= e($field_id) ?>">
    <textarea
        id="<?= e($field_id) ?>"
        name="<?= e($field_name) ?>"
        style="display:none;"><?= e($field_value) ?></textarea>
    <div data-documents-markdown-editor-target></div>
</div>

<?php
echo \Zen\Chub\Classes\Support\Vite::tags([
    'documents_editor/js/documents_editor.js'
]);
