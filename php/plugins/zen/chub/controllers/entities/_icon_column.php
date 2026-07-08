<?php
/**
 * Partial для отображения SVG иконки в списке
 * 
 * @var \Backend\Classes\ListColumn $column
 * @var \Model $record
 * @var mixed $value
 */

$icon_path = $record->icon_path ?? null;

if (!$icon_path) {
    echo '<span class="text-muted">—</span>';
    return;
}

// Формируем полный путь к файлу
$full_path = base_path($icon_path);

if (!file_exists($full_path)) {
    echo '<span class="text-muted">—</span>';
    return;
}

// Читаем содержимое SVG файла
$svg_content = file_get_contents($full_path);

if (!$svg_content) {
    echo '<span class="text-muted">—</span>';
    return;
}

// Добавляем inline стили для размера
$svg_content = preg_replace(
    '/<svg([^>]*)>/i',
    '<svg$1 style="width: 24px; height: 24px; display: inline-block; vertical-align: middle;">',
    $svg_content,
    1
);

echo $svg_content;
