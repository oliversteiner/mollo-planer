<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler: one cron entry runs `php artisan schedule:run` every minute.
Schedule::command('app:prune-unverified-users')->daily();

// Authorization server: expired and revoked tokens and codes go after a week (Passport), the notes of the codes
// (what the id_token needs) after a day, a code lives ten minutes.
Schedule::command('passport:purge')->daily();
Schedule::call(fn () => DB::table('mcp_tool_calls')->where('created_at', '<', now()->subDays(90))->delete())
    ->name('mcp-prune-tool-calls')
    ->daily();
Schedule::call(fn () => DB::table('oauth_auth_code_claims')->where('created_at', '<', now()->subDay())->delete())
    ->name('oauth-prune-code-claims')
    ->daily();

// Mails and webhooks wait in the queue. On the host there is no permanent worker: every minute the scheduler
// starts one that works until the queue is empty (docs/deploy-template.md, section Cron).
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
