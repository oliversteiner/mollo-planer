<?php

namespace App\Core\Identity\Policies;

use App\Enums\Right;
use App\Models\User;
use App\Support\Rights;

/** Who manages the applications that may log in through the planer: those with the right settings.manage (admins). */
class OAuthClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function revoke(User $user): bool
    {
        return $this->manage($user);
    }

    private function manage(User $user): bool
    {
        return Rights::allows($user, Right::SETTINGS_MANAGE);
    }
}
