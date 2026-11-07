<?php

namespace App\Support;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

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
    private const OWNER = ProjectRole::Owner;

    private const MANAGER = ProjectRole::Manager;

    private const MEMBER = ProjectRole::Member;

    /**
     * @var array<string, list<ProjectRole>>
     */
    private const ROLES_BY_ABILITY = [
        ProjectAbility::ProjectUpdate->value => [self::OWNER, self::MANAGER],
        ProjectAbility::ProjectDelete->value => [self::OWNER],
        ProjectAbility::MembersManage->value => [self::OWNER, self::MANAGER],
        ProjectAbility::MilestoneCreate->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::MilestoneUpdate->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::MilestoneDelete->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TodoCreate->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TodoUpdate->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TodoDelete->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TaskCreate->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TaskUpdate->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TaskAssign->value => [self::OWNER, self::MANAGER],
        ProjectAbility::TaskMove->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::TaskDelete->value => [self::OWNER, self::MANAGER],
        ProjectAbility::CommentCreate->value => [self::OWNER, self::MANAGER, self::MEMBER],
        ProjectAbility::CommentModerate->value => [self::OWNER, self::MANAGER],
    ];

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
