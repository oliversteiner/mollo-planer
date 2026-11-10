<?php

namespace App\Policies;

use App\Enums\SystemAbility;
use App\Models\LabelSet;
use App\Models\User;
use App\Support\SystemPermissions;

/**
 * Label sets are app-wide. Everybody signed in sees them; creating and
 * duplicating needs the system ability labelSets.manage. Own sets are
 * changed and deleted by their creator (with that ability) or by anyone
 * with labelSets.manageAny; built-in sets by nobody.
 */
class LabelSetPolicy
{
    public function create(User $user): bool
    {
        return SystemPermissions::allows($user, SystemAbility::LABEL_SETS_MANAGE);
    }

    public function duplicate(User $user, LabelSet $set): bool
    {
        return $this->create($user);
    }

    public function update(User $user, LabelSet $set): bool
    {
        return ! $set->isBuiltin()
            && (SystemPermissions::allows($user, SystemAbility::LABEL_SETS_MANAGE_ANY)
                || ((int) $set->created_by === (int) $user->getKey() && $this->create($user)));
    }

    public function delete(User $user, LabelSet $set): bool
    {
        return $this->update($user, $set);
    }
}
