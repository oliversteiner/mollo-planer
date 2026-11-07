<?php

namespace App\Policies\Concerns;

use App\Enums\ProjectAbility;
use App\Models\Contracts\BelongsToProject;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectPermissions;

trait ChecksProjectAbilities
{
    /**
     * Ask the permission table for the project the model belongs to.
     */
    protected function allows(User $user, Project|BelongsToProject $model, ProjectAbility $ability): bool
    {
        $project = $model instanceof Project ? $model : $model->owningProject();

        return ProjectPermissions::allows($user, $project, $ability);
    }
}
