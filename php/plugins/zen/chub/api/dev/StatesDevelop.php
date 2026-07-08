<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\StatesApp;

class StatesDevelop
{
    # http://axis/chub.api/Dev.StatesDevelop:testSetting
    public function testSetting()
    {
        dd(
            StatesApp::getSetting('logs-rotation.logs_period')
        );
    }
}