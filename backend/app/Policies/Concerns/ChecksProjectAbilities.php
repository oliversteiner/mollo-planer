<?php

namespace App\Policies\Concerns;

use App\Enums\Right;
use App\Models\Contracts\BelongsToProject;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;

trait ChecksProjectAbilities
{
    /**
     * Ask the rights of the project the model belongs to.
     */
    protected function allows(User $user, Project|BelongsToProject $model, Right $right): bool
    {
        $project = $model instanceof Project ? $model : $model->owningProject();

        return Rights::allows($user, $right, $project);
    }
}
