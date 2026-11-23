<?php

namespace App\Providers;

use App\Planer\PlanerRevision;
use App\Services\DocumentAccessService;
use App\Services\TaskNotifier;
use App\Support\DeploymentEnvironment;
use App\Support\PasswordPolicy;
use App\Support\Rights;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request, so withoutAssignmentMails() reaches the
        // notifier the GraphQL mutations use.
        $this->app->scoped(TaskNotifier::class);

        // Remembers the projects of one transaction; per request and per queued job.
        $this->app->scoped(PlanerRevision::class);

        // Remembers the shares read for a list of documents; per request.
        $this->app->scoped(DocumentAccessService::class);

        // The check against known leaked passwords waits 3 seconds at most (Laravel's own: 30).
        $this->app->extend(UncompromisedVerifier::class, PasswordPolicy::verifier());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => PasswordPolicy::defaults());

        // TEST holds the real planning data (Auftrag 37 D): on both server environments (staging, production) the commands that
        // empty or replace the database refuse to run, with or without --force: migrate:fresh, migrate:refresh, migrate:reset,
        // migrate:rollback, db:wipe and db:seed.
        // DB::prohibitDestructiveCommands() leaves db:seed out, so it is added here.
        DB::prohibitDestructiveCommands(DeploymentEnvironment::isLive());
        SeedCommand::prohibit(DeploymentEnvironment::isLive());

        // Outside the live environments (production and staging), loading a relation per row (N+1) is an error.
        // GraphQL lists batch-load instead (@with, ProgressBatchLoader).
        Model::preventLazyLoading(! DeploymentEnvironment::isLive());

        // Escape Markdown links and HTML in values echoed into mails, so a
        // task title like "[Click](https://evil.example)" stays plain text.
        Markdown::withSecuredEncoding();

        // A queue worker lives longer than a request: read the rights anew
        // for every job, so a change in the settings reaches it.
        Event::listen(JobProcessing::class, fn () => Rights::clearCache());

        // MCP requests (Claude) per minute and token owner; a project plan is
        // one request, reading a project another.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(60)->by('mcp:'.$request->user()?->getAuthIdentifier()));

        // GraphQL requests per minute, per user (or per IP for guests).
        RateLimiter::for('graphql', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute((int) config('lighthouse.rate_limit_per_minute', 120))
                ->by($user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip());
        });
    }
}
