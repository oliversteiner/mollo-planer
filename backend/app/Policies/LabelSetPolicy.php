<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\LabelSet;
use App\Models\User;
use App\Support\ProjectPermissions;

/**
 * Label sets are app-wide. Everybody signed in sees them; managing them
 * needs LABEL_SETS_MANAGE in any project. Own sets are changed and deleted
 * by their creator or an admin; built-in sets by nobody.
 */
class LabelSetPolicy
{
    public function create(User $user): bool
    {
        return ProjectPermissions::allowsInAnyProject($user, ProjectAbility::LABEL_SETS_MANAGE);
    }

    public function duplicate(User $user, LabelSet $set): bool
    {
        return $this->create($user);
    }

    public function update(User $user, LabelSet $set): bool
    {
        return ! $set->isBuiltin()
            && ($user->is_admin || ((int) $set->created_by === (int) $user->getKey() && $this->create($user)));
    }

    public function delete(User $user, LabelSet $set): bool
    {
        return $this->update($user, $set);
    }
}
