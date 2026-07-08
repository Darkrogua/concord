<div class="form-group">
    <div>
        <?php $preview_table = $this->getPreviewTableForUi(); ?>
        <h4>Управление базой</h4>
        <p>Сервисные действия для SQLite-базы этого экземпляра.</p>

        <button
            class="btn btn-danger oc-icon-refresh"
            data-request="onClearBase"
            data-request-confirm="Пересоздать SQLite-базу для этого экземпляра?"
            data-request-flash
            data-stripe-load-indicator>
            Пересоздать базу
        </button>
        <button
            class="btn btn-warning oc-icon-trash"
            data-request="onClearRecordsTable"
            data-request-confirm="Очистить таблицу <?= e($preview_table) ?> для этого экземпляра?"
            data-request-flash
            data-stripe-load-indicator>
            Очистить таблицу <?= e($preview_table) ?>
        </button>
    </div>
</div>

<div id="base-info-progress">
    <?= $this->makePartial('info_progress') ?>
</div>

<div id="base-info-records">
    <?= $this->makePartial('info_records') ?>
</div>

<div id="base-info-tables">
    <?= $this->makePartial('info_tables') ?>
</div>

<button
    id="base-info-progress-refresh"
    type="button"
    style="display:none;"
    data-request="onRefreshBaseInfoProgress">
</button>

<script>
(function () {
    var timer_key = '__chub_base_info_progress_timer';
    if (window[timer_key]) {
        clearInterval(window[timer_key]);
    }

    var refresh_button = document.getElementById('base-info-progress-refresh');
    if (!refresh_button) {
        return;
    }

    window[timer_key] = setInterval(function () {
        if (!document.body.contains(refresh_button)) {
            clearInterval(window[timer_key]);
            window[timer_key] = null;
            return;
        }

        refresh_button.click();
    }, 2000);
})();
</script>
