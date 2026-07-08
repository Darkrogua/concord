<?php
/**
 * Chub-команда (id=40): удалённая prod-сборка Vite (make vite-prod-build / vite-prod-build.sh).
 * Вывод npm/ssh в строке return — попап результата и лог (при output_log).
 */
use Zen\Chub\Controllers\Deploy;
use Zen\Chub\Classes\Support\SupportRocketBot;

if (env('APP_ENV') === 'production') {
    SupportRocketBot::make()->send('Запущена автоматическая сборка микрофронтендов chub');
}

$result = Deploy::runViteProdBuildScript();

if (env('APP_ENV') === 'production') {
    SupportRocketBot::make()->send('Сборка микрофронтендов chub успешно завершена');
}

return $result['message'];