<?php

namespace App\Support;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single service that answers "may this person do this?". Policies ask
 * only this class. The standard rights are read from config/permissions.php;
 * no other code compares roles.
 *
 * Rules that are not rights (nobody edits someone else's comment, the last
 * owner stays) belong in the respective policy or in ProjectMembership.
 */
final class Rights
{
    /** Read-only users may not change anything, whatever their project role. */
    public static function isReadOnly(User $user): bool
    {
        return $user->role === UserRole::LESEND;
    }

    /**
     * Does this user have the system-wide right? Guests have none.
     */
    public static function systemAllows(?User $user, SystemAbility $ability): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($ability, self::systemRightsOf($user->role), true);
    }

    /**
     * System rights of a role, in the order of the enum.
     *
     * @return list<SystemAbility>
     */
    public static function systemRightsOf(?UserRole $role): array
    {
        if ($role === null) {
            return [];
        }

        $granted = (array) config('permissions.system.'.$role->value, []);

        if (in_array('*', $granted, true)) {
            return SystemAbility::cases();
        }

        return array_values(array_filter(
            SystemAbility::cases(),
            fn (SystemAbility $ability): bool => in_array($ability->value, $granted, true),
        ));
    }

    /**
     * Does this project role have the project right? A missing role has none.
     */
    public static function projectRoleAllows(?ProjectRole $role, ProjectAbility $ability): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($ability, self::projectRightsOfRole($role), true);
    }

    /**
     * Project rights of a role, in the order of the enum.
     *
     * @return list<ProjectAbility>
     */
    public static function projectRightsOfRole(?ProjectRole $role): array
    {
        if ($role === null) {
            return [];
        }

        $granted = (array) config('permissions.project.'.$role->value, []);

        return array_values(array_filter(
            ProjectAbility::cases(),
            fn (ProjectAbility $ability): bool => in_array($ability->value, $granted, true),
        ));
    }

    /**
     * May this user do this in this project?
     */
    public static function projectAllows(User $user, Project $project, ProjectAbility $ability): bool
    {
        if (self::isReadOnly($user)) {
            return false;
        }

        if (self::systemAllows($user, SystemAbility::PROJECTS_MANAGE_ANY)) {
            return true;
        }

        return self::projectRoleAllows($project->roleOf($user), $ability);
    }

    /**
     * Does the user have this project right in at least one project?
     * Used for things outside a single project, such as the member search.
     */
    public static function projectAllowsInAnyProject(User $user, ProjectAbility $ability): bool
    {
        if (self::isReadOnly($user)) {
            return false;
        }

        if (self::systemAllows($user, SystemAbility::PROJECTS_MANAGE_ANY)) {
            return true;
        }

        return $user->projects()
            ->pluck('project_user.role')
            ->contains(fn (string $role) => self::projectRoleAllows(ProjectRole::tryFrom($role), $ability));
    }

    /**
     * Everything this user may do in this project (Project.viewerAbilities).
     *
     * @return list<ProjectAbility>
     */
    public static function projectRightsOf(User $user, Project $project): array
    {
        if (self::isReadOnly($user)) {
            return [];
        }

        if (self::systemAllows($user, SystemAbility::PROJECTS_MANAGE_ANY)) {
            return ProjectAbility::cases();
        }

        return self::projectRightsOfRole($project->roleOf($user));
    }

    /**
     * May the actor change a membership from $currentRole to $newRole?
     * Null means "not a member" (add) or "remove".
     */
    public static function canChangeMembership(User $actor, Project $project, ?ProjectRole $currentRole, ?ProjectRole $newRole): bool
    {
        if (! self::projectAllows($actor, $project, ProjectAbility::MEMBERS_MANAGE)) {
            return false;
        }

        $touchesOwner = $currentRole === ProjectRole::OWNER || $newRole === ProjectRole::OWNER;

        return ! $touchesOwner
            || self::systemAllows($actor, SystemAbility::PROJECTS_MANAGE_ANY)
            || $project->roleOf($actor) === ProjectRole::OWNER;
    }

    /**
     * May the actor remove or demote the last owner? Only users who may
     * manage any project.
     */
    public static function canRemoveLastOwner(User $actor): bool
    {
        return self::systemAllows($actor, SystemAbility::PROJECTS_MANAGE_ANY);
    }

    /**
     * May this user see the project at all? Users who may view all projects
     * and members can.
     */
    public static function canView(User $user, Project $project): bool
    {
        return self::systemAllows($user, SystemAbility::PROJECTS_VIEW_ALL)
            || $project->roleOf($user) !== null;
    }

    /**
     * The query version of canView(): restrict a project query to the
     * projects the user may see. Guests see nothing, admins everything.
     *
     * @param  Builder<Project>  $projects
     */
    public static function constrainToVisibleProjects(Builder $projects, ?User $user): void
    {
        if ($user === null) {
            $projects->whereRaw('1 = 0');

            return;
        }

        if (self::systemAllows($user, SystemAbility::PROJECTS_VIEW_ALL)) {
            return;
        }

        $projects->whereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
    }
}
