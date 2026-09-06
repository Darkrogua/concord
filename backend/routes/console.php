<?php

use App\Jobs\ArchiveCompletedAgreementsJob;
use App\Jobs\CheckAgreementCompletionJob;
use App\Jobs\PublishScheduledAgreementsJob;
use App\Jobs\SendDeadlineReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new PublishScheduledAgreementsJob)->everyMinute();
Schedule::job(new ArchiveCompletedAgreementsJob)->hourly();
Schedule::job(new CheckAgreementCompletionJob)->everyFiveMinutes();
Schedule::job(new SendDeadlineReminderJob)->hourly();
