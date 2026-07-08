<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\CommandApp;

class CommandDevelop
{
    # http://axis/chub.api/Dev.CommandDevelop:test
    public function test()
    {
        $result = CommandApp::exec('logs-rotate');
        dd($result);
    }
}