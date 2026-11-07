<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectPermissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes and removes project members.
 *
 * Rules on top of "members.manage" (checked by the policy):
 * - Only owners and admins grant the owner role or change/remove an owner.
 * - The last owner cannot be removed or demoted, except by an admin.
 * - Removing a member unassigns their tasks in this project.
 */
class ProjectMembership
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function set(User $actor, Project $project, User $user, ProjectRole $role): void
    {
        $current = $project->roleOf($user);

        $this->authorize($actor, $project, $current, $role);

        if ($current === ProjectRole::OWNER && $role !== ProjectRole::OWNER) {
            $this->guardLastOwner($actor, $project, 'role');
        }

        if ($current === null) {
            $project->members()->attach($user, ['role' => $role->value]);
        } else {
            $project->members()->updateExistingPivot($user->getKey(), ['role' => $role->value]);
        }
    }

    /**
     * Removing someone who is not a member does nothing.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function remove(User $actor, Project $project, User $user): void
    {
        $current = $project->roleOf($user);

        if ($current === null) {
            return;
        }

        $this->authorize($actor, $project, $current, null);

        if ($current === ProjectRole::OWNER) {
            $this->guardLastOwner($actor, $project, 'userId');
        }

        $project->members()->detach($user);

        Task::query()
            ->inProject($project->getKey())
            ->where('assigned_user_id', $user->getKey())
            ->update(['assigned_user_id' => null]);
    }

    /**
     * Throws a validation error unless the user is a member of the project.
     *
     * @throws ValidationException
     */
    public function ensureAssignable(Project $project, int|string $userId, string $field): void
    {
        $user = User::find($userId);

        if ($user === null || $project->roleOf($user) === null) {
            throw ValidationException::withMessages([$field => __('projects.assignee_not_member')]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, Project $project, ?ProjectRole $current, ?ProjectRole $new): void
    {
        if (! ProjectPermissions::canChangeMembership($actor, $project, $current, $new)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardLastOwner(User $actor, Project $project, string $field): void
    {
        if ($project->countMembersWithRole(ProjectRole::OWNER) <= 1 && ! ProjectPermissions::canRemoveLastOwner($actor)) {
            throw ValidationException::withMessages([$field => __('projects.last_owner')]);
        }
    }
}
