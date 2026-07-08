<?php

use Zen\Chub\Classes\System\BasesApp;

$count = BasesApp::connect('uon-payments')
    ->query('records')
    ->where('created_at', '>', now()->format('Y-m-d'))
    ->count();

BasesApp::connect('uon-payments')
    ->query('records')
    ->where('created_at', '>', now()->format('Y-m-d'))
    ->delete();

return "Удалено записей: $count";