<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production, loading a relation per row (N+1) is an error.
        // GraphQL lists batch-load instead (@with, ProgressBatchLoader).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Escape Markdown links and HTML in values echoed into mails, so a
        // task title like "[Click](https://evil.example)" stays plain text.
        Markdown::withSecuredEncoding();

        // GraphQL requests per minute, per user (or per IP for guests).
        RateLimiter::for('graphql', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute((int) config('lighthouse.rate_limit_per_minute', 120))
                ->by($user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip());
        });
    }
}
