<?php

namespace App\Support;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single source of truth for project permissions.
 *
 * Policies ask only this class. No other code compares roles. To restrict
 * or extend an ability, change its line in ROLES_BY_ABILITY.
 *
 * Admins (users.is_admin) have every ability. Rules that are not project
 * abilities (for example: nobody, not even an admin, edits someone else's
 * comment) belong in the respective policy.
 */
final class ProjectPermissions
{
    private const OWNER = ProjectRole::OWNER;

    private const MANAGER = ProjectRole::MANAGER;

    private const MEMBER = ProjectRole::MEMBER;

    /**
     * @var array<string, list<ProjectRole>>
     */
    private const ROLES_BY_ABILITY = [
        ProjectAbility::PROJECT_UPDATE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::PROJECT_DELETE->value => [self::OWNER],
        ProjectAbility::MEMBERS_MANAGE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::MILESTONE_CREATE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::MILESTONE_UPDATE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::MILESTONE_DELETE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TODO_CREATE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TODO_UPDATE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TODO_DELETE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TASK_CREATE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TASK_UPDATE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TASK_ASSIGN->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TASK_MOVE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TASK_DELETE->value => [self::OWNER, self::MANAGER],
        ProjectAbility::COMMENT_CREATE->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::COMMENT_MODERATE->value => [self::OWNER, self::MANAGER],
    ];

    /**
     * Roles that may grant the owner role or change or remove an owner, on
     * top of "members.manage". Without this, a manager could make
     * themselves owner. Admins may always.
     *
     * @var list<ProjectRole>
     */
    private const OWNER_MANAGERS = [self::OWNER];

    /**
     * May the actor change a membership from $currentRole to $newRole?
     * Null means "not a member" (add) or "remove".
     */
    public static function canChangeMembership(User $actor, Project $project, ?ProjectRole $currentRole, ?ProjectRole $newRole): bool
    {
        if (! self::allows($actor, $project, ProjectAbility::MEMBERS_MANAGE)) {
            return false;
        }

        $touchesOwner = $currentRole === self::OWNER || $newRole === self::OWNER;

        return ! $touchesOwner
            || $actor->is_admin
            || in_array($project->roleOf($actor), self::OWNER_MANAGERS, true);
    }

    /**
     * May the actor leave a project without any owner (remove or demote
     * the last owner)? Only admins, for example when staff changes.
     */
    public static function canRemoveLastOwner(User $actor): bool
    {
        return $actor->is_admin;
    }

    /**
     * Does this role have the ability? A missing role (no member) has none.
     */
    public static function roleAllows(?ProjectRole $role, ProjectAbility $ability): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, self::ROLES_BY_ABILITY[$ability->value] ?? [], true);
    }

    /**
     * Abilities of a role, in the order of the enum.
     *
     * @return list<ProjectAbility>
     */
    public static function abilitiesOfRole(?ProjectRole $role): array
    {
        return array_values(array_filter(
            ProjectAbility::cases(),
            fn (ProjectAbility $ability) => self::roleAllows($role, $ability),
        ));
    }

    /**
     * May this user do this in this project?
     */
    public static function allows(User $user, Project $project, ProjectAbility $ability): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return self::roleAllows($project->roleOf($user), $ability);
    }

    /**
     * May this user see the project at all? Admins and members can.
     */
    public static function canView(User $user, Project $project): bool
    {
        return $user->is_admin || $project->roleOf($user) !== null;
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

        if ($user->is_admin) {
            return;
        }

        $projects->whereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
    }

    /**
     * Does the user have this ability in at least one project?
     * Used for things outside a single project, such as the user list.
     */
    public static function allowsInAnyProject(User $user, ProjectAbility $ability): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $user->projects()
            ->pluck('project_user.role')
            ->contains(fn (string $role) => self::roleAllows(ProjectRole::tryFrom($role), $ability));
    }

    /**
     * Everything this user may do in this project (Project.viewerAbilities).
     *
     * @return list<ProjectAbility>
     */
    public static function abilities(User $user, Project $project): array
    {
        if ($user->is_admin) {
            return ProjectAbility::cases();
        }

        return self::abilitiesOfRole($project->roleOf($user));
    }
}
