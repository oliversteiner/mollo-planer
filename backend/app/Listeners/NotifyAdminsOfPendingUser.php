<?php

namespace App\Listeners;

use App\Enums\Right;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use App\Support\Rights;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;

/**
 * After the mail address is confirmed, the admins hear about the account
 * that waits for approval. Only people with the right users.manage get the
 * mail.
 */
class NotifyAdminsOfPendingUser
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isApproved()) {
            return;
        }

        $admins = User::query()
            ->whereIn('role_id', Rights::roleIdsWith(Right::USERS_MANAGE))
            ->whereNotNull('approved_at')
            ->get();

        Notification::send($admins, new UserAwaitingApproval($user->name));
    }
}
