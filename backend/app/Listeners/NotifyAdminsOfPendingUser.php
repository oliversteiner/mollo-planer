<?php

namespace App\Listeners;

use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use App\Support\SystemPermissions;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;

/**
 * After the mail address is confirmed, the admins hear about the account
 * that waits for approval. Only users who manage users get the mail, the
 * roles come from App\Support\SystemPermissions.
 */
class NotifyAdminsOfPendingUser
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->isApproved()) {
            return;
        }

        $roles = array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => in_array(
                SystemAbility::USERS_MANAGE,
                SystemPermissions::abilitiesOf($role),
                true,
            ),
        );

        $admins = User::query()
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles))
            ->whereNotNull('approved_at')
            ->get();

        Notification::send($admins, new UserAwaitingApproval($user->name));
    }
}
