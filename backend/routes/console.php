<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler: one cron entry runs `php artisan schedule:run` every minute.
Schedule::command('app:prune-unverified-users')->daily();

// Mails and webhooks wait in the queue. On the host there is no permanent worker: every minute the scheduler
// starts one that works until the queue is empty (docs/deploy-template.md, section Cron).
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
