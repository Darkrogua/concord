<button
    type="button"
    class="btn btn-warning"
    data-control="popup"
    data-handler="onRunCommandPopup"
    data-size="large"
    data-load-indicator="Выполнение команды...">
    <i class="icon-play"></i>
    Запустить команду
</button>

<?php if ($show_calls_count): ?>
    <span class="btn-text" style="margin-left: 12px;">
        Вызовов: <strong><?= (int) $calls_count ?></strong>
    </span>
<?php endif; ?>
