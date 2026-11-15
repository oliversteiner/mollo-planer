<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes and removes project members.
 *
 * Rules on top of "members.manage" (checked by the policy), see
 * docs/rollen-funktionen-rechte.md, §7. They follow rights, not role names:
 * - A role with project.delete or owner.manage is granted, changed or
 *   removed only with owner.manage (or projects.manageAny).
 * - The project keeps at least one member who may delete it. Only someone
 *   with projects.manageAny may break this.
 * - Removing a member unassigns their tasks in this project.
 *
 * Call inside a transaction: changes lock the project row, so two changes
 * to the same project (for example two owners demoting each other) run
 * one after the other and the last-owner check stays correct.
 */
class ProjectMembership
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function set(User $actor, Project $project, User $user, Role $role): void
    {
        $this->lockProject($project);

        $current = $project->roleOf($user);

        $this->authorize($actor, $project, $current, $role);

        if (Rights::holdsProject($user, $current) && ! Rights::holdsProject($user, $role)) {
            $this->guardLastHolder($actor, $project, $user, 'role');
        }

        if ($current === null) {
            $project->members()->attach($user, ['role_id' => $role->id]);
        } else {
            $project->members()->updateExistingPivot($user->getKey(), ['role_id' => $role->id]);
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
        $this->lockProject($project);

        $current = $project->roleOf($user);

        if ($current === null) {
            return;
        }

        $this->authorize($actor, $project, $current, null);

        if (Rights::holdsProject($user, $current)) {
            $this->guardLastHolder($actor, $project, $user, 'userId');
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

        if ($user === null || $project->roleIdOf($user) === null) {
            throw ValidationException::withMessages([$field => __('projects.assignee_not_member')]);
        }
    }

    private function lockProject(Project $project): void
    {
        Project::query()->whereKey($project->getKey())->lockForUpdate()->first();
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, Project $project, ?Role $current, ?Role $new): void
    {
        if (! Rights::canChangeMembership($actor, $project, $current, $new)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws ValidationException
     */
    private function guardLastHolder(User $actor, Project $project, User $leaving, string $field): void
    {
        if (Rights::countHolders($project, $leaving) === 0 && ! Rights::canLeaveProjectWithoutHolder($actor)) {
            throw ValidationException::withMessages([$field => __('projects.last_owner')]);
        }
    }
}
