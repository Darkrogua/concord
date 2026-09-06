<?php

namespace App\Jobs;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Services\VoteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckAgreementCompletionJob implements ShouldQueue
{
    use Queueable;

    public function handle(VoteService $votes): void
    {
        Agreement::query()
            ->where('status', AgreementStatus::Awaiting)
            ->with('sections')
            ->each(fn (Agreement $agreement) => $votes->expireIfNeeded($agreement));
    }
}
