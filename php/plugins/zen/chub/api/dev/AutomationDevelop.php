<?php namespace Zen\Chub\Api\Dev;

use Illuminate\Validation\Rules\NotIn;
use Zen\Chub\Classes\Connectors\Uon;
use Zen\Chub\Classes\Support\Transformers;
use Zen\Chub\Classes\Connectors\TelegramBot;
use Zen\Chub\Classes\System\BasesApp;

use Zen\Chub\Classes\Automations\DeadlineNotifications;
use Zen\Chub\Classes\Automations\RequestNotifications;
use Zen\Chub\Classes\System\Files;
use Zen\Chub\Classes\System\Monitoring;
use Zen\Chub\Classes\System\Sqlite;
use Zen\Chub\Classes\Automations\UonPaymentsAutomation;
use Zen\Chub\Classes\Automations\ManagersShiftNotificationAutomation;


class AutomationDevelop
{
    public static function make(): self
    {
        return new self();
    }

    # http://axis/chub.api/Dev.AutomationDevelop:testUPN
    public function testUPN()
    {
        UonPaymentsAutomation::make()->handle();
    }

    # http://axis/chub.api/Dev.AutomationDevelop:testNotify
    public function testNotify()
    {
        ManagersShiftNotificationAutomation::make()->handle();
    }

    # http://axis/chub.api/Dev.AutomationDevelop:resetNow
    public function resetNow()
    {
        return;
        $count = BasesApp::connect('uon-payments')
            ->query('records')
            ->where('created_at', '>', now()->format('Y-m-d'))
            ->count();

        BasesApp::connect('uon-payments')
            ->query('records')
            ->where('created_at', '>', now()->format('Y-m-d'))
            ->delete();

        return "Удалено записей: $count";
    }
}