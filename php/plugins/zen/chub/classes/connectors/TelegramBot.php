<?php namespace Zen\Chub\Classes\Connectors;

use Illuminate\Support\Facades\Http;

class TelegramBot
{
    private string $bot_token;

    /** @var int|string group/supergroup id может быть отрицательным int */
    private $chat_id;

    public function __construct(?string $bot_token = null, $chat_id = null)
    {
        $this->bot_token = (string) ($bot_token ?? env('AUTOMATION_TELEGRAM_BOT_TOKEN', ''));
        $raw = $chat_id ?? env('AUTOMATION_TELEGRAM_CHAT_ID');
        $this->chat_id = ($raw === null || $raw === '') ? 0 : (int) $raw;
    }

    public static function make(?string $bot_token = null, $chat_id = null): self
    {
        return new self($bot_token, $chat_id);
    }

    /**
     * @param  string|null  $parse_mode  HTML, MarkdownV2 или null — без parse_mode (удобно для произвольного текста)
     */
    public function sendMessage(string $text, ?string $parse_mode = 'HTML'): void
    {
        if ($this->bot_token === '' || $this->chat_id === 0) {
            return;
        }

        $payload = [
            'chat_id' => $this->chat_id,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($parse_mode !== null && $parse_mode !== '') {
            $payload['parse_mode'] = $parse_mode;
        }

        Http::timeout(20)->post(
            "https://api.telegram.org/bot{$this->bot_token}/sendMessage",
            $payload
        );
    }

    # Удаление сообщения в Telegram.
    public function deleteMessage(int $message_id): void
    {
        if ($this->bot_token && $this->chat_id) {
            $api_url = 'https://api.telegram.org';
            $query = "$api_url/bot{$this->bot_token}/deleteMessage?chat_id={$this->chat_id}&message_id=$message_id";
            Http::get($query);
        }
    }
}