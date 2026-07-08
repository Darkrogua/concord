<?php

use Zen\Chub\Classes\Support\SupportRocketBot;

$text = $input_data['text'] ?? input('text');

$secret = 'ng6Gdj4Knfb7Fd2h4jg5ccd3asidygrhbfkasyugf';

# Проверка секрета
$api_allow = (input('secret') ?? $input_data['secret'] ?? null) === $secret;

if (!$api_allow) {
    return 'secret не обнаружен';
}

# Не вызывать SupportRocketBot::send() — снова вызовет эту команду и исчерпает память.
SupportRocketBot::deliverToRocket((string) $text);

return "Отправлен текст: $text";