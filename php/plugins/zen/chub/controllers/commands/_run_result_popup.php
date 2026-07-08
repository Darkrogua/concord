<style>
.result-body {
    padding: 15px;
    background: #1d6c8f;
    color: #fff;
    border-radius: 5px;
    margin-bottom: 10px!important;
}
</style>
<div class="modal-header" style="position: relative;">
    <button
        type="button"
        class="close"
        data-dismiss="popup"
        style="position: absolute; right: 15px; top: 10px; float: none; margin: 0;">
        &times;
    </button>
    <h4 class="modal-title"><?= e($command_popup_title ?? 'Результат') ?></h4>
</div>

<div class="modal-body">
    <pre class="result-body" style="white-space: pre-wrap; word-break: break-word; margin: 0;"><?= e($command_popup_content ?? '') ?></pre>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="popup">Закрыть</button>
</div>

<script>
    $.request('onRefreshCommandRunFragments');
</script>
