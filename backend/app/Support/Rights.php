<?php

namespace App\Support;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The single service that answers "may this person do this?". Policies ask
 * only this class. The rights of a role come from the tables roles,
 * functions, function_rights and role_functions (seeded from
 * config/permissions.php). No other code compares roles.
 *
 * Rules that are not rights (nobody edits someone else's comment, the last
 * owner stays) belong in the respective policy or in ProjectMembership.
 */
final class Rights
{
    /**
     * Rights and project cap per role, read once per request.
     *
     * @var array<string, array{rights: list<string>, cap: list<string>|null}>
     */
    private static array $grants = [];

    /** Forget what was read, after the rights changed. */
    public static function clearCache(): void
    {
        self::$grants = [];
    }

    /**
     * May this person do this? A system right needs no project. A project
     * right needs the project it is asked for; without one the answer is no.
     * Guests (no user) have no right.
     */
    public static function allows(?User $user, Right $right, ?Project $project = null): bool
    {
        if ($user === null) {
            return false;
        }

        if ($right->isSystem()) {
            return in_array($right, self::systemRightsOf($user->role), true);
        }

        if ($project === null) {
            return false;
        }

        if (self::allows($user, Right::PROJECTS_MANAGE_ANY)) {
            return true;
        }

        return self::withinCap($user, $right)
            && self::projectRoleAllows($project->roleOf($user), $right);
    }

    /**
     * Everything this person may do: the system rights without a project
     * (viewerRights), the project rights within one (Project.viewerRights).
     *
     * @return list<Right>
     */
    public static function rightsOf(User $user, ?Project $project = null): array
    {
        if ($project === null) {
            return self::systemRightsOf($user->role);
        }

        if (self::allows($user, Right::PROJECTS_MANAGE_ANY)) {
            return Right::ofScope(Right::SCOPE_PROJECT);
        }

        return array_values(array_filter(
            self::projectRightsOfRole($project->roleOf($user)),
            fn (Right $right): bool => self::withinCap($user, $right),
        ));
    }

    /**
     * System rights of a role, in the order of the enum.
     *
     * @return list<Right>
     */
    public static function systemRightsOf(?UserRole $role): array
    {
        return $role === null ? [] : self::rightsOfRole(Right::SCOPE_SYSTEM, $role->value);
    }

    /**
     * Does this project role have the project right? A missing role has none.
     */
    public static function projectRoleAllows(?ProjectRole $role, Right $right): bool
    {
        return in_array($right, self::projectRightsOfRole($role), true);
    }

    /**
     * Project rights of a role, in the order of the enum.
     *
     * @return list<Right>
     */
    public static function projectRightsOfRole(?ProjectRole $role): array
    {
        return $role === null ? [] : self::rightsOfRole(Right::SCOPE_PROJECT, $role->value);
    }

    /**
     * Rights of the role with this key, in the order of the enum. Rights of
     * the other scope and unknown texts in the table grant nothing.
     *
     * @return list<Right>
     */
    public static function rightsOfRole(string $scope, string $key): array
    {
        $granted = self::grantOf($scope, $key)['rights'];

        return array_values(array_filter(
            Right::ofScope($scope),
            fn (Right $right): bool => in_array($right->value, $granted, true),
        ));
    }

    /**
     * Does the user have this project right in at least one project?
     * Used for things outside a single project, such as the member search.
     */
    public static function allowsInAnyProject(User $user, Right $right): bool
    {
        if (self::allows($user, Right::PROJECTS_MANAGE_ANY)) {
            return true;
        }

        if (! self::withinCap($user, $right)) {
            return false;
        }

        return $user->projects()
            ->pluck('project_user.role')
            ->contains(fn (string $role) => self::projectRoleAllows(ProjectRole::tryFrom($role), $right));
    }

    /**
     * May the actor change a membership from $currentRole to $newRole?
     * Null means "not a member" (add) or "remove". A role that carries
     * project.delete or owner.manage is granted or taken away only with
     * owner.manage (docs/rollen-funktionen-rechte.md, §7.4). The rule looks
     * at the rights of the roles, never at their names.
     */
    public static function canChangeMembership(User $actor, Project $project, ?ProjectRole $currentRole, ?ProjectRole $newRole): bool
    {
        if (! self::allows($actor, Right::MEMBERS_MANAGE, $project)) {
            return false;
        }

        $touchesOwnership = self::isOwnershipRole($currentRole) || self::isOwnershipRole($newRole);

        return ! $touchesOwnership || self::allows($actor, Right::OWNER_MANAGE, $project);
    }

    /**
     * Does the role carry project.delete or owner.manage? Such a role is
     * handled only by people with owner.manage.
     */
    public static function isOwnershipRole(?ProjectRole $role): bool
    {
        return self::projectRoleAllows($role, Right::PROJECT_DELETE)
            || self::projectRoleAllows($role, Right::OWNER_MANAGE);
    }

    /**
     * Would this member keep the project alive, that is: may they delete it
     * through their project role (within the cap of their system role)?
     * projects.manageAny does not count, it is no membership.
     */
    public static function holdsProject(User $member, ?ProjectRole $role): bool
    {
        return self::projectRoleAllows($role, Right::PROJECT_DELETE)
            && self::withinCap($member, Right::PROJECT_DELETE);
    }

    /**
     * How many members hold the project (see holdsProject), not counting
     * $except. Every project keeps at least one (§7.3).
     */
    public static function countHolders(Project $project, ?User $except = null): int
    {
        return $project->members()
            ->get()
            ->reject(fn (User $member): bool => $except !== null && $member->is($except))
            ->filter(fn (User $member): bool => self::holdsProject($member, $member->membership->role))
            ->count();
    }

    /**
     * How many approved people have this system right, not counting $except.
     * Used for the rule that somebody always keeps users.manage and
     * roles.manage (§7.1).
     */
    public static function countUsersWith(Right $right, ?User $except = null): int
    {
        $roles = array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => in_array($right, self::systemRightsOf($role), true),
        ));

        return User::query()
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, $roles))
            ->whereNotNull('approved_at')
            ->when($except !== null, fn (Builder $users) => $users->whereKeyNot($except->getKey()))
            ->count();
    }

    /**
     * May the actor assign the task to $assigneeId? With task.assign any
     * assignment is allowed. With only task.assignSelf the actor may assign
     * the task to themselves, or remove the assignment if it is theirs.
     */
    public static function mayAssignTask(User $actor, Project $project, int|string|null $assigneeId, int|string|null $currentAssigneeId): bool
    {
        if (self::allows($actor, Right::TASK_ASSIGN, $project)) {
            return true;
        }

        if (! self::allows($actor, Right::TASK_ASSIGN_SELF, $project)) {
            return false;
        }

        $self = (string) $actor->getKey();

        return (string) $assigneeId === $self
            || ($assigneeId === null && (string) $currentAssigneeId === $self);
    }

    /**
     * May the actor leave a project without anybody who holds it? Only
     * people who may manage any project (§7.3).
     */
    public static function canLeaveProjectWithoutHolder(User $actor): bool
    {
        return self::allows($actor, Right::PROJECTS_MANAGE_ANY);
    }

    /**
     * May this user see the project at all? Users who may view all projects
     * and members can.
     */
    public static function canView(User $user, Project $project): bool
    {
        return self::allows($user, Right::PROJECTS_VIEW_ALL)
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

        if (self::allows($user, Right::PROJECTS_VIEW_ALL)) {
            return;
        }

        $projects->whereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
    }

    /** The project cap of the user's system role allows this right (null cap = all). */
    private static function withinCap(User $user, Right $right): bool
    {
        $cap = self::grantOf('system', $user->role?->value ?? '')['cap'];

        return $cap === null || in_array($right->value, $cap, true);
    }

    /**
     * Rights and cap of one role. All roles are read in one query the first
     * time, so the number of queries does not grow with the number of roles.
     *
     * @return array{rights: list<string>, cap: list<string>|null}
     */
    private static function grantOf(string $scope, string $key): array
    {
        if (self::$grants === []) {
            self::loadGrants();
        }

        return self::$grants[$scope.':'.$key] ?? ['rights' => [], 'cap' => []];
    }

    private static function loadGrants(): void
    {
        $rows = DB::table('roles')
            ->leftJoin('role_functions', 'role_functions.role_id', '=', 'roles.id')
            ->leftJoin('function_rights', 'function_rights.function_id', '=', 'role_functions.function_id')
            ->select(['roles.scope', 'roles.key', 'roles.project_cap', 'function_rights.right'])
            ->get();

        $grants = [];
        foreach ($rows as $row) {
            $cacheKey = $row->scope.':'.$row->key;
            $grants[$cacheKey] ??= [
                'rights' => [],
                'cap' => $row->project_cap === null ? null : json_decode($row->project_cap, true),
            ];
            if ($row->right !== null) {
                $grants[$cacheKey]['rights'][] = $row->right;
            }
        }

        foreach ($grants as $cacheKey => $grant) {
            $grants[$cacheKey]['rights'] = array_values(array_unique($grant['rights']));
        }

        self::$grants = $grants;
    }
}
