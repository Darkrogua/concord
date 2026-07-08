<div class="modal-header">
    <h4 class="modal-title"><?= e($record_popup_title ?? 'Просмотр записи') ?></h4>
    <button type="button" class="btn-close" data-dismiss="popup"></button>
</div>

<div class="modal-body">
    <?= $record_form_widget->render(['preview' => true, 'useContainer' => false]) ?>
</div>

<div class="modal-footer">
    <button
        type="button"
        class="btn btn-default"
        data-dismiss="popup">
        Закрыть
    </button>
</div>
