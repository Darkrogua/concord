/*
 * При клике по телу строки-папки (без URL) ретранслирует клик на кнопку expand/collapse.
 * Строки-папки имеют класс list-row-folder.
 */
+function ($) {
    'use strict';

    $(document).on('click', '[data-control="liststructurewidget"] tr.list-row-folder', function (evt) {
        var $row = $(evt.currentTarget);

        // Не перехватывать клик по самой кнопке expand/collapse
        if ($(evt.target).closest('.tree-expand-collapse').length) {
            return;
        }

        // Не перехватывать клик по чекбоксу
        if ($(evt.target).closest('input[type="checkbox"]').length) {
            return;
        }

        // Не перехватывать клик по ручке перетаскивания
        if ($(evt.target).closest('.list-reorder-handle').length) {
            return;
        }

        var $expandBtn = $row.find('.tree-expand-collapse');
        if ($expandBtn.length) {
            $expandBtn[0].click();
        }
    });
}(window.jQuery);
