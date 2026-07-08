<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\Tests\HandlersTests;
use Zen\Chub\Classes\System\CronApp;
use Zen\Chub\Classes\System\ProcessApp;
use Illuminate\Console\Scheduling\Schedule;

class CronsDevelop
{
    # http://axis/chub.api/Dev.CronsDevelop:simpleHandlerTest
    public function simpleHandlerTest()
    {
        HandlersTests::make()->pannerTest();
    }

    # http://axis/chub.api/Dev.CronsDevelop:CronAppTest
    public function CronAppTest()
    {
       //CronApp::make()->execute(app(Schedule::class));
       ProcessApp::make()->runScheduleProcess('sheduller-tests');
    }
}