<div data-control="toolbar">
    <a
        href="<?= Backend::url('zen/chub/states/create') ?>"
        class="btn btn-primary oc-icon-plus">
        <?= e(trans('backend::lang.form.create')) ?>
    </a>
    <button
        class="btn btn-default oc-icon-trash-o"
        data-request="onDelete"
        data-request-confirm="<?= e(trans('backend::lang.list.delete_selected_confirm')) ?>"
        data-list-checked-trigger
        data-list-checked-request
        data-stripe-load-indicator>
        <?= e(trans('backend::lang.list.delete_selected')) ?>
    </button>
    <button
        class="btn btn-default oc-icon-save"
        data-request="onSaveData"
        data-request-flash
        data-stripe-load-indicator>
        Сохранить данные
    </button>
    <button
        class="btn btn-default oc-icon-refresh"
        data-request="onRestoreData"
        data-request-flash
        data-stripe-load-indicator>
        Восстановить данные
    </button>
</div>

<div class="callout fade show callout-warning" style="margin-top: 14px; max-width: 920px;">
    <div class="header">
        <h3>⚠️ Настройки хранятся в файлах</h3>
    </div>
    <div class="content">
        <p>
            Значения полей состояний записываются в JSON в каталоге
            <code>plugins/zen/chub/data/states/</code>
            (имя файла: <code>state_</code> + код состояния + <code>.json</code>, например
            <code>state_demo.json</code> для кода <code>demo</code>).
        </p>
        <p class="text-muted" style="margin-bottom: 0;">
            Файлы настроек состояний (<code>state_*.json</code> в этом каталоге) не отслеживаются git — при смене окружения или деплое их нужно переносить или восстанавливать вручную.
        </p>
    </div>
</div>
