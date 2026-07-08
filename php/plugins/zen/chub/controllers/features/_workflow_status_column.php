<?php
/**
 * @var \Zen\Chub\Models\Feature $record
 */

$labels = [
    '' => 'Не начата',
    'in_progress' => 'В работе',
    'ready' => 'Готова',
];

$status = (string) ($record->workflow_status ?? '');
echo e($labels[$status] ?? $status);
