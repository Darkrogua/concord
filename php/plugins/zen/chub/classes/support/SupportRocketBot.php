<?php namespace Zen\Chub\Classes\Support;

use Zen\Chub\Classes\Connectors\RocketChatBot;
use Zen\Chub\Classes\System\CommandApp;

class SupportRocketBot
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Точка входа с приложения: всегда через команду `support.bot` (лог CommandApp, если включён).
     * Не вызывать из тела той же команды — иначе бесконечная рекурсия.
     */
    public function send(string $message)
    {
        CommandApp::exec('support.bot', [
            'text' => $message,
            'secret' => 'ng6Gdj4Knfb7Fd2h4jg5ccd3asidygrhbfkasyugf',
        ]);
    }

    /**
     * Прямая отправка в Rocket.Chat. Используется только внутри команды `support.bot`
     * после проверки секрета; сюда нельзя прокидывать внешние вызовы, если нужен лог по команде.
     */
    public static function deliverToRocket(string $message_text): void
    {
        RocketChatBot::make()->sendMessage(
            text: $message_text,
            web_hook_url: 'https://chat-varuna.os3.pro/hooks/autopost-development-cursor-bridge/rcb_dev_ut5b5r8ka7d'
        );
    }
}