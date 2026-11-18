<?php

namespace App\Policies\Concerns;

use App\Enums\Right;
use App\Models\Contracts\BelongsToProject;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;

trait ChecksProjectAbilities
{
    /**
     * Ask the rights of the project the model belongs to. Refused because
     * the project is archived: the denial says so (Rights::refusedByArchive).
     */
    protected function allows(User $user, Project|BelongsToProject $model, Right $right): bool
    {
        $project = $model instanceof Project ? $model : $model->owningProject();

        if (Rights::refusedByArchive($user, $right, $project)) {
            $this->refuseAsArchived();
        }

        return Rights::allows($user, $right, $project);
    }

    /**
     * Ends the check with the message for an archived project (Gate::inspect
     * turns it into a denial, so can() answers false).
     *
     * @throws AuthorizationException
     */
    protected function refuseAsArchived(): never
    {
        throw new AuthorizationException(__('projects.archived'));
    }
}
