<?php namespace Zen\Chub\Api\Automation;

use Zen\Chub\Classes\Automations\UonPaymentsAutomation;

class Notificator
{
    # Вебхук с U-ON для автоматизации уведомлений
    # http://axis/chub.api/Automation.Notificator:uonWebhookCreatePayment
    public function uonWebhookCreatePayment()
    {
        UonPaymentsAutomation::make()->handle();
    }
}