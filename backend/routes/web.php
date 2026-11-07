<?php

use App\Http\Controllers\Auth\AcceptInvitationController;
use Illuminate\Support\Facades\Route;

// Fortify registers login, logout, 2FA, passkey and password routes itself.

Route::post('/invitation', AcceptInvitationController::class)
    ->middleware(['guest:web', 'throttle:6,1'])
    ->name('invitation.accept');
