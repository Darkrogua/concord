<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Support\SupportRocketBot;

class SupportRocketBotDevelop
{
    # http://axis/chub.api/Dev.SupportRocketBotDevelop:test
    public function test()
    {
        SupportRocketBot::make()->send('test');
    }
}