<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\LogsApp;

class LogDevelop
{
    # http://axis/chub.api/Dev.LogDevelop:testLog
    public function testLog()
    {
        LogsApp::addInfo([
            'Тут какая-то инфа'
        ], 'lol');
    }
}