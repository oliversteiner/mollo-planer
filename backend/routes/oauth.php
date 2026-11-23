<?php

use App\Core\Identity\Http\Controllers\ConsentController;
use App\Core\Identity\Http\Controllers\DiscoveryController;
use App\Core\Identity\Http\Controllers\JwksController;
use App\Core\Identity\Http\Controllers\UserInfoController;
use App\Core\Identity\Http\Middleware\EnsureMayAuthorize;
use App\Core\Identity\Http\Middleware\EnsureStrongAuthenticationForOAuth;
use App\Core\Identity\Http\Middleware\RememberAuthorizationClaims;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

// Authorization server (Passport routes with our middleware; the names stay, laravel/mcp builds on them).
// The browser is sent to /oauth/authorize, which leads to the consent page of the frontend; the page answers
// with the JSON routes below (session cookie, CSRF as for every call of the frontend).
Route::prefix('oauth')->name('passport.')->group(function (): void {
    Route::post('/token', [AccessTokenController::class, 'issueToken'])
        ->middleware('throttle:oauth-token')
        ->name('token');

    Route::get('/authorize', [AuthorizationController::class, 'authorize'])
        ->middleware(['web', 'throttle:oauth-authorize', EnsureStrongAuthenticationForOAuth::class, EnsureMayAuthorize::class, RememberAuthorizationClaims::class])
        ->name('authorizations.authorize');

    Route::middleware(['web', 'auth:web', 'throttle:oauth-authorize'])->group(function (): void {
        Route::get('/consent', [ConsentController::class, 'show'])->middleware(EnsureMayAuthorize::class)->name('consent.show');
        Route::post('/consent/approve', [ConsentController::class, 'approve'])->middleware(EnsureMayAuthorize::class)->name('consent.approve');
        Route::post('/consent/deny', [ConsentController::class, 'deny'])->name('consent.deny');
    });
});

// OpenID Connect: discovery, the key of the id_tokens, and the claims about the person of an access token.
Route::get('/.well-known/openid-configuration', DiscoveryController::class)
    ->middleware('throttle:oauth-token')
    ->name('oidc.configuration');
Route::get('/.well-known/jwks.json', JwksController::class)
    ->middleware('throttle:oauth-token')
    ->name('oidc.jwks');
Route::match(['get', 'post'], '/oauth/userinfo', UserInfoController::class)
    ->middleware(['throttle:oauth-token', 'auth:oauth'])
    ->name('oidc.userinfo');
