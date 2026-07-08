<?php

use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Classes\System\StatesApp;



$db = Sqlite::connect(storage_path('chub/bases/logs.sqlite'));
$days = intval(StatesApp::getSetting('logs-rotation.logs_period'));

$count = $db->query('records')
    ->where('created_at', '<', now()->subDays($days)->toDateTimeString())
    ->count();

    $db->query('records')
    ->where('created_at', '<', now()->subDays($days)->toDateTimeString())
    ->delete();

return "Удалено записей: $count";