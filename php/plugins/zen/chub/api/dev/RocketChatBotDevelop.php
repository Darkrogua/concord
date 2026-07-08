<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Connectors\RocketChatBot;

class RocketChatBotDevelop
{
    # http://axis/chub.api/Dev.RocketChatBotDevelop:testbot
    public function testbot()
    {
        RocketChatBot::make()->sendMessage('<b>Тестовое уведомление</b><a href="https://azimuth-tour.kaiten.ru/space/712806/boards/card/63404745">Ссылка</a>');
    }
}