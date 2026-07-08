<?php
/**
 * Partial для кнопки "Перейти" в списке
 * 
 * @var \Backend\Classes\ListColumn $column
 * @var \Model $record
 * @var mixed $value
 */

use Backend\Facades\Backend;

$backend_url = Backend::url('zen/chub/entities/update/' . $record->id);

?>
<a 
    href="<?= e($backend_url) ?>" 
    class="btn btn-sm btn-default oc-icon-cog empty"
    title="Управление"
    onclick="event.stopPropagation(); return true;"
></a>
