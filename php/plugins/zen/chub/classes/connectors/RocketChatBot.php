<?php namespace Zen\Chub\Classes\Connectors;

use Illuminate\Support\Facades\Http;
use Zen\Chub\Classes\System\LogsApp;

class RocketChatBot
{
    /** Incoming workflow webhook (Rocket.Chat / Cursor workflow). */
    private const WEBHOOK_URL = 'https://chat-varuna.os3.pro/hooks/incoming-workflow-cursor/wf_khdeuhm1apl';

    /** Попыток отправки и пауза между ними (нестабильный DNS / сеть). */
    private const SEND_MAX_ATTEMPTS = 3;
    private const SEND_RETRY_DELAY_MS = 2000;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Сигнатура совпадает с {@see TelegramBot::sendMessage}: тот же текст уходит в вебхук.
     * Режим HTML: подмножество Telegram HTML переводится в Markdown для Rocket.Chat (**жирный**, ссылки).
     *
     * @param  string|null  $parse_mode  HTML, MarkdownV2 или null — как в Telegram
     * @param  string|null  $web_hook_url  Если задан — запрос уходит на этот incoming webhook (другой канал / бот).
     */
    public function sendMessage(string $text, ?string $parse_mode = 'HTML', ?string $web_hook_url = null): void
    {
        $payloadText = $this->prepareWebhookText($text, $parse_mode);
        $url = $web_hook_url !== null && $web_hook_url !== '' ? $web_hook_url : self::WEBHOOK_URL;
        $webhook_host = parse_url($url, PHP_URL_HOST);

        try {
            $response = Http::timeout(20)
                ->retry(self::SEND_MAX_ATTEMPTS, self::SEND_RETRY_DELAY_MS)
                ->asJson()
                ->post($url, ['text' => $payloadText]);

            if (!$response->successful()) {
                LogsApp::addError([
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'webhook_host' => $webhook_host,
                ], 'RocketChat: HTTP ошибка отправки');
            }
        } catch (\Throwable $e) {
            LogsApp::addErrorFromThrowable($e, 'RocketChat: исключение при отправке', [
                'webhook_host' => $webhook_host,
            ]);
            throw $e;
        }
    }

    private function prepareWebhookText(string $text, ?string $parse_mode): string
    {
        if ($parse_mode === null || $parse_mode === '') {
            return $text;
        }

        if ($parse_mode === 'HTML') {
            return $this->telegramHtmlToRocketMarkdown($text);
        }

        return $text;
    }

    /**
     * Telegram HTML → Markdown в духе Rocket.Chat (поле text вебхука парсится как Markdown).
     */
    private function telegramHtmlToRocketMarkdown(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"?><div id="telegram-html-root">' . $html . '</div>';
        if (!@$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $root = $doc->getElementById('telegram-html-root');
        if ($root === null) {
            return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->domNodeToRocketMarkdown($root);
    }

    private function domNodeToRocketMarkdown(\DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $out .= $this->escapeRocketMarkdownPlain($child->data);

                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->tagName);

            if ($name === 'a') {
                $out .= $this->anchorToRocketMarkdown($child);

                continue;
            }

            $inner = $this->domNodeToRocketMarkdown($child);

            $out .= match ($name) {
                'b', 'strong' => '**' . $inner . '**',
                'i', 'em' => '_' . $inner . '_',
                'u' => $inner,
                's', 'strike', 'del' => '~~' . $inner . '~~',
                'code' => '`' . str_replace('`', "'", $inner) . '`',
                'pre' => "```\n" . $inner . "\n```\n",
                'br' => "\n",
                'p', 'div' => $inner === '' ? '' : $inner . "\n",
                default => $inner,
            };
        }

        return $out;
    }

    private function anchorToRocketMarkdown(\DOMElement $a): string
    {
        $href = $a->getAttribute('href');
        $label = $this->domNodeToRocketMarkdown($a);

        if ($href === '') {
            return $label;
        }

        return '[' . $label . '](' . $href . ')';
    }

    /**
     * Экранирование символов разметки в обычном тексте (не внутри уже сформированных ** и т.д.).
     * Квадратные скобки не экранируем: иначе Rocket.Chat показывает «\[» и «\]» буквально.
     * Ссылки в Markdown для вебхука собираем только из разметки в domNodeToRocketMarkdown (тег a).
     */
    private function escapeRocketMarkdownPlain(string $s): string
    {
        static $map = [
            '\\' => '\\\\',
            '*' => '\\*',
            '_' => '\\_',
            '`' => '\\`',
        ];

        return strtr($s, $map);
    }
}