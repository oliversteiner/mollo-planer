<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

// Fortify registers login, logout, 2FA, passkey and password routes itself.

Route::post('/invitation', AcceptInvitationController::class)
    ->middleware(['guest:web', 'throttle:6,1'])
    ->name('invitation.accept');

// Status page (frontend): application and database. Public, rate limited, no data.
Route::get('/status', StatusController::class)
    ->middleware('throttle:30,1')
    ->name('status');
