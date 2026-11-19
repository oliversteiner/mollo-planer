<?php

namespace App\Providers;

use App\Actions\Fortify\RedirectIfTwoFactorEnabled;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorEnabled::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Forgot password and reset password: per IP, like registration (5 a minute), so nobody can flood
        // mailboxes or try reset tokens in bulk. Fortify gives these two routes no limiter of their own.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by('password-reset:'.$request->ip());
        });

        $this->app->booted(function (): void {
            Route::getRoutes()->refreshNameLookups();

            foreach (['password.email', 'password.update'] as $name) {
                Route::getRoutes()->getByName($name)?->middleware('throttle:password-reset');
            }
        });

        // The link of the mail (open and confirm): generous per IP, many people share one address (office, mobile network).
        // The link is signed, so guessing is no risk; the limit only keeps a flood away.
        RateLimiter::for('verification-link', function (Request $request) {
            return Limit::perMinute(20)->by('verify-link:'.$request->ip());
        });

        // Mail again without a session: few per hour, by address and by IP, so nobody can flood a mailbox.
        RateLimiter::for('verification-guest', function (Request $request) {
            return [
                Limit::perHour(3)->by('verify-ip:'.$request->ip()),
                Limit::perHour(3)->by('verify-mail:'.Str::lower(trim((string) $request->input('email')))),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
