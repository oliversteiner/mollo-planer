<?php

use App\Core\Identity\Http\Controllers\ConsentController;
use App\Core\Identity\Http\Middleware\EnsureStrongAuthenticationForOAuth;
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
        ->middleware(['web', 'throttle:oauth-authorize', EnsureStrongAuthenticationForOAuth::class])
        ->name('authorizations.authorize');

    Route::middleware(['web', 'auth:web', 'throttle:oauth-authorize'])->group(function (): void {
        Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
        Route::post('/consent/approve', [ConsentController::class, 'approve'])->name('consent.approve');
        Route::post('/consent/deny', [ConsentController::class, 'deny'])->name('consent.deny');
    });
});
