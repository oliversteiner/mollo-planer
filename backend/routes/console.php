<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler: one cron entry runs `php artisan schedule:run` every minute.
Schedule::command('app:prune-unverified-users')->daily();
