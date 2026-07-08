<?php namespace Zen\Chub\Console;

use Illuminate\Console\Command;
use Zen\Chub\Classes\Tools\DevBotApp;

class DevBotCommand extends Command
{
    protected $signature = 'chub:notice
        {--message= : Текст сообщения}
        {--stdin : Прочитать сообщение из stdin (удобно для git hooks)}
        {--plain : Отправить как простой текст без HTML (рекомендуется с --stdin)}';

    protected $description = 'Уведомление DevBot: Rocket.Chat (DEV_BOT_ROCKETCHAT_WEBHOOK_URL) или Telegram (DEV_BOT_TELEGRAM_*)';

    public function handle(): int
    {
        if ($this->option('stdin')) {
            $message = stream_get_contents(STDIN);
        } else {
            $message = (string) $this->option('message');
        }

        $message = trim($message);
        if ($message === '') {
            return self::SUCCESS;
        }

        $plain = (bool) $this->option('stdin') || (bool) $this->option('plain');

        DevBotApp::make()->send($message, $plain);

        return self::SUCCESS;
    }
}