<?php

namespace App\Jobs;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendDeadlineReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notifications): void
    {
        $count = 0;
        Agreement::query()
            ->where('status', AgreementStatus::Awaiting)
            ->whereBetween('deadline', [now()->addDay()->startOfHour(), now()->addDay()->endOfHour()])
            ->each(function (Agreement $agreement) use ($notifications, &$count): void {
                $notifications->notifyDeadlineApproaching($agreement);
                $count++;
            });

        if ($count > 0) {
            Log::info('job.deadline_reminder', [
                'event' => 'job.deadline_reminder',
                'count' => $count,
            ]);
        }
    }
}
