<?php
/**
 * Иконка папки или потока в списке (папки — только для группировки в UI).
 *
 * @var \Backend\Classes\ListColumn $column
 * @var \Model $record
 * @var mixed $value
 */

$icon_path = (bool) ($record->is_folder ?? false)
    ? 'plugins/zen/chub/assets/build/main/images/entities/folder.svg'
    : 'plugins/zen/chub/assets/build/main/images/entities/atom.svg';

$full_path = base_path($icon_path);

if (!file_exists($full_path)) {
    echo '<span class="text-muted">—</span>';
    return;
}

$icon_url = '/' . str_replace('\\', '/', $icon_path);

printf(
    '<img src="%s" width="24" height="24" alt="" loading="lazy" style="display: inline-block; vertical-align: middle;">',
    e($icon_url)
);
