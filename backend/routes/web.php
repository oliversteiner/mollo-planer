<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// Fortify registers login, logout, 2FA, passkey and password routes itself.

Route::post('/invitation', AcceptInvitationController::class)
    ->middleware(['guest:web', 'throttle:6,1'])
    ->name('invitation.accept');

// Self-registration. Replaces the route of Fortify (feature off): a taken address gets the same answer as a new one.
Route::post('/register', RegisterController::class)
    ->middleware(['guest:web', 'throttle:register'])
    ->name('register.store');

// Confirmation of the e-mail address. These replace the routes of Fortify (feature switched off in
// config/fortify.php): the link must work without a session and must not confirm on a mere call.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'show'])
    ->middleware('throttle:verification-link')
    ->name('verification.verify');
Route::post('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'confirm'])
    ->middleware('throttle:verification-link');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:web', 'throttle:1,1'])
    ->name('verification.send');
Route::post('/email/verification-notification/guest', [EmailVerificationController::class, 'resendToAddress'])
    ->middleware('throttle:verification-guest')
    ->name('verification.send-guest');

// Status page (frontend): application and database. Public, rate limited, no data.
Route::get('/status', StatusController::class)
    ->middleware('throttle:30,1')
    ->name('status');

// Agents look for the MCP guide on the API host first: send them to the static guide of the frontend (Auftrag 49 D).
foreach (['/mcp/llms.txt', '/llms.txt'] as $guide) {
    Route::get($guide, fn () => redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/mcp/llms.txt', 301));
}
