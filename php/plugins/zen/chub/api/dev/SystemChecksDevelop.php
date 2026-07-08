<?php namespace Zen\Chub\Api\Dev;

use Zen\Chub\Classes\System\SystemChecks;

class SystemChecksDevelop
{
    # http://axis/chub.api/Dev.SystemChecksDevelop:check1
    public function check1()
    {
        dd(
            SystemChecks::make()->freeSpaceAutoCheck()
        );
        
    }

    # http://axis/chub.api/Dev.SystemChecksDevelop:check2
    public function check2()
    {
        return SystemChecks::make()->heardBeatCheck();
    }

    # http://axis/chub.api/Dev.SystemChecksDevelop:check3
    public function check3()
    {
        return SystemChecks::make()->logsCheck();
    }

    public function indicatorTest()
    {
        return SystemChecks::make()->indicator(true);
    }

    # http://axis/chub.api/Dev.SystemChecksDevelop:allChecks
    public function allChecks()
    {
        return SystemChecks::make()->dailyCheck();
    }
}