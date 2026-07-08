<?php
/**
 * @var \Backend\Classes\ListColumn $column
 * @var \Zen\Chub\Models\Feature $record
 */

$icon_path = 'plugins/zen/chub/assets/build/main/images/entities/feature.svg';
$full_path = base_path($icon_path);

if (! file_exists($full_path)) {
    echo '<span class="text-muted">—</span>';

    return;
}

$icon_url = '/'.str_replace('\\', '/', $icon_path);

printf(
    '<img src="%s" width="24" height="24" alt="" loading="lazy" style="display: inline-block; vertical-align: middle;">',
    e($icon_url)
);
