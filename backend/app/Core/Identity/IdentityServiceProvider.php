<?php

namespace App\Core\Identity;

use App\Core\Identity\Oidc\IdTokenResponse;
use App\Core\Identity\Oidc\OidcAuthCodeGrant;
use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;

/**
 * The planer as an authorization server (OAuth 2.1, later OpenID Connect): Passport with our own routes, so that
 * the authorization page is the frontend's and every step keeps the strong-authentication lock of the app.
 * Part of the base (module Identity, docs/starter-kit.md): imports nothing from the planer's own code.
 */
class IdentityServiceProvider extends ServiceProvider
{
    /** Scopes of the authorization server. The text is in lang/<locale>/oauth.php. */
    public const SCOPES = ['openid', 'profile', 'email', 'api', 'mcp:use', 'mcp:read', 'mcp:write'];

    public function register(): void
    {
        // The routes of Passport are replaced by routes/oauth.php (same names, other middleware).
        Passport::ignoreRoutes();

        // The tokens carry an id_token for the scope openid, and the code grant notes what it needs.
        Passport::useAuthorizationServerResponseType(new IdTokenResponse);
        $this->app->extend(AuthorizationServer::class, function (AuthorizationServer $server): AuthorizationServer {
            $grant = new OidcAuthCodeGrant($this->app->make(AuthCodeRepository::class), $this->app->make(RefreshTokenRepository::class), new DateInterval('PT10M'));
            $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
            $server->enableGrantType($grant, Passport::tokensExpireIn());

            return $server;
        });
    }

    public function boot(): void
    {
        Passport::tokensExpireIn(Date::now()->addHour());
        Passport::refreshTokensExpireIn(Date::now()->addDays(90));
        Passport::tokensCan(array_combine(self::SCOPES, array_map(fn (string $scope): string => 'oauth.scopes.'.$scope, self::SCOPES)));

        // The page where the person agrees is a page of the frontend; it loads the details from /oauth/consent.
        Passport::authorizationView(fn (array $parameters) => redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/oauth/consent'));

        RateLimiter::for('oauth-authorize', fn (Request $request) => Limit::perMinute(30)->by('oauth-authorize:'.$request->ip()));
        RateLimiter::for('oauth-token', fn (Request $request) => Limit::perMinute(60)->by('oauth-token:'.$request->ip()));

        Route::middleware([])->group(__DIR__.'/../../../routes/oauth.php');
    }
}
