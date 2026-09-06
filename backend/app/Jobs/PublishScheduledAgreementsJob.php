<?php

namespace App\Jobs;

use App\Services\AgreementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishScheduledAgreementsJob implements ShouldQueue
{
    use Queueable;

    public function handle(AgreementService $agreements): void
    {
        $agreements->publishScheduled();
    }
}
