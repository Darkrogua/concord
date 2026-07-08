<?php namespace Zen\Chub\Classes\Tools;

use Zen\Chub\Classes\Connectors\RocketChatBot;
use Zen\Chub\Classes\Connectors\TelegramBot;

class DevBotApp
{
    public static function make(): self
    {
        return new self();
    }

    /**
     * Канал DevBot / chub:notice / git hook после pull:
     * — приоритет: DEV_BOT_ROCKETCHAT_WEBHOOK_URL → Rocket.Chat (config services.devbot);
     * — иначе (legacy): Telegram через те же ключи в config.
     * Значения задаются в .env, но читаются через config(), чтобы работало при php artisan config:cache.
     *
     * @param  bool  $plainText  true — без HTML (git hook, произвольный текст)
     */
    public function send(string $message, bool $plainText = false): void
    {
        $rocket_url = trim((string) config('services.devbot.rocketchat_webhook_url', ''));
        if ($rocket_url !== '') {
            RocketChatBot::make()->sendMessage(
                $message,
                $plainText ? null : 'HTML',
                $rocket_url
            );

            return;
        }

        $token = (string) config('services.devbot.telegram_bot_token', '');
        $chatRaw = config('services.devbot.telegram_chat_id', '');

        TelegramBot::make($token, $chatRaw)->sendMessage($message, $plainText ? null : 'HTML');
    }
}