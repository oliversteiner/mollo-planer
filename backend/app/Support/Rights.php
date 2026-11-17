<?php

namespace App\Support;

use App\Enums\Right;
use App\Models\Project;
use App\Models\Role;
use App\Models\RoleFunction;
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
     * @var array<int, array{rights: list<string>, cap: list<string>|null}>
     */
    private static array $grants = [];

    /**
     * Rights per function, read once per request.
     *
     * @var array<int, list<string>>|null
     */
    private static ?array $functionRights = null;

    /** Forget what was read, after the rights changed. */
    public static function clearCache(): void
    {
        self::$grants = [];
        self::$functionRights = null;
        Role::forgetRegistry();
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
            return in_array($right->value, self::grantOf($user->role_id)['rights'], true);
        }

        if ($project === null || ! self::withinArchive($project, $right)) {
            return false;
        }

        if (self::allows($user, Right::PROJECTS_MANAGE_ANY)) {
            return true;
        }

        return self::withinCap($user, $right)
            && self::roleAllows($project->roleOf($user), $right);
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
            return self::rightsOfRole($user->role);
        }

        $rights = self::allows($user, Right::PROJECTS_MANAGE_ANY)
            ? Right::ofScope(Right::SCOPE_PROJECT)
            : array_filter(self::rightsOfRole($project->roleOf($user)), fn (Right $right): bool => self::withinCap($user, $right));

        return array_values(array_filter($rights, fn (Right $right): bool => self::withinArchive($project, $right)));
    }

    /**
     * The rights a role grants, in the order of the enum. Rights of the
     * other scope and unknown texts in the table grant nothing. A missing
     * role has none.
     *
     * @return list<Right>
     */
    public static function rightsOfRole(?Role $role): array
    {
        if ($role === null) {
            return [];
        }

        $granted = self::grantOf($role->id)['rights'];

        return array_values(array_filter(
            Right::ofScope($role->scope),
            fn (Right $right): bool => in_array($right->value, $granted, true),
        ));
    }

    /**
     * The rights of a function, in the order of the enum. Rights of the
     * other scope and unknown texts in the table are left out.
     *
     * @return list<Right>
     */
    public static function rightsOfFunction(RoleFunction $function): array
    {
        if (self::$functionRights === null) {
            self::$functionRights = [];
            foreach (DB::table('function_rights')->get(['function_id', 'right']) as $row) {
                self::$functionRights[(int) $row->function_id][] = $row->right;
            }
        }

        $stored = self::$functionRights[$function->id] ?? [];

        return array_values(array_filter(
            Right::ofScope($function->scope),
            fn (Right $right): bool => in_array($right->value, $stored, true),
        ));
    }

    /** Does the role grant the right? A missing role grants nothing. */
    public static function roleAllows(?Role $role, Right $right): bool
    {
        return $role !== null
            && $right->scope() === $role->scope
            && in_array($right->value, self::grantOf($role->id)['rights'], true);
    }

    /**
     * System rights of a system role. Takes the role or the enum of a
     * shipped key (seeders, tests).
     *
     * @return list<Right>
     */
    public static function systemRightsOf(?Role $role): array
    {
        return self::rightsOfRole($role);
    }

    /**
     * Does this project role have the project right? Takes the role or the
     * enum of a shipped key (seeders, tests).
     */
    public static function projectRoleAllows(?Role $role, Right $right): bool
    {
        return self::roleAllows($role, $right);
    }

    /**
     * @return list<Right>
     */
    public static function projectRightsOfRole(?Role $role): array
    {
        return self::rightsOfRole($role);
    }

    /**
     * Ids of the roles that grant the right.
     *
     * @return list<int>
     */
    public static function roleIdsWith(Right $right): array
    {
        return array_values(array_map(
            fn (Role $role): int => $role->id,
            array_filter(Role::ofScope($right->scope()), fn (Role $role): bool => self::roleAllows($role, $right)),
        ));
    }

    /**
     * May the actor change a membership from $currentRole to $newRole?
     * Null means "not a member" (add) or "remove". A role that carries
     * project.delete or owner.manage is granted or taken away only with
     * owner.manage (docs/rollen-funktionen-rechte.md, §7.4). The rule looks
     * at the rights of the roles, never at their names.
     */
    public static function canChangeMembership(User $actor, Project $project, ?Role $currentRole, ?Role $newRole): bool
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
    public static function isOwnershipRole(?Role $role): bool
    {
        return self::projectRoleAllows($role, Right::PROJECT_DELETE)
            || self::projectRoleAllows($role, Right::OWNER_MANAGE);
    }

    /**
     * Would this member keep the project alive, that is: may they delete it
     * through their project role (within the cap of their system role)?
     * projects.manageAny does not count, it is no membership.
     */
    public static function holdsProject(User $member, ?Role $role): bool
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
     * Ids of the projects somebody holds (see holdsProject), in one query.
     * The settings compare this before and after a change of roles or
     * functions, so no project loses its last holder that way (§7.3).
     * Projects in the trash do not count (docs/auftrag-m2.md §0a.3).
     *
     * @return list<int>
     */
    public static function projectIdsWithHolder(): array
    {
        $uncapped = array_map(
            fn (Role $role): int => $role->id,
            array_filter(Role::ofScope(Role::SCOPE_SYSTEM), function (Role $role): bool {
                $cap = self::grantOf($role->id)['cap'];

                return $cap === null || in_array(Right::PROJECT_DELETE->value, $cap, true);
            }),
        );

        return DB::table('project_user')
            ->join('users', 'users.id', '=', 'project_user.user_id')
            ->join('projects', 'projects.id', '=', 'project_user.project_id')
            ->whereNull('projects.deleted_at')
            ->whereIn('project_user.role_id', self::roleIdsWith(Right::PROJECT_DELETE))
            ->whereIn('users.role_id', $uncapped)
            ->distinct()
            ->orderBy('project_user.project_id')
            ->pluck('project_user.project_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * How many approved people have this system right, not counting $except.
     * Used for the rule that somebody always keeps users.manage and
     * roles.manage (§7.1).
     */
    public static function countUsersWith(Right $right, ?User $except = null): int
    {
        return User::query()
            ->whereIn('role_id', self::roleIdsWith($right))
            ->whereNotNull('approved_at')
            ->when($except !== null, fn (Builder $users) => $users->whereKeyNot($except->getKey()))
            ->count();
    }

    /**
     * May the actor assign the task to $assigneeId? With task.assign any
     * assignment is allowed. With only task.assignSelf the actor may assign
     * the task to themselves, or remove the assignment if it is theirs.
     * Leaving the assignment as it is needs no right (a new task without
     * assignee, docs/fix-m1-abnahme.md F3.1); creating or changing the task
     * is checked by its policy.
     */
    public static function mayAssignTask(User $actor, Project $project, int|string|null $assigneeId, int|string|null $currentAssigneeId): bool
    {
        if ((string) $assigneeId === (string) $currentAssigneeId) {
            return true;
        }

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
     * The projects that would be left without a holder if this person had
     * the system role $systemRole (null: the person is deleted). These are
     * the projects the person holds alone, when the cap of the new role
     * takes project.delete away. Every project keeps a holder, whoever acts
     * (§7.3, docs/fix-m1-abnahme.md F3.4). Projects in the trash do not count
     * (SoftDeletes): restoring one takes care of its holder (Deletion).
     *
     * @return list<Project>
     */
    public static function projectsOnlyHeldBy(User $user, ?Role $systemRole): array
    {
        if ($systemRole !== null) {
            $cap = self::grantOf($systemRole->id)['cap'];

            if ($cap === null || in_array(Right::PROJECT_DELETE->value, $cap, true)) {
                return [];
            }
        }

        return $user->projects()
            ->whereIn('project_user.role_id', self::roleIdsWith(Right::PROJECT_DELETE))
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => self::withinCap($user, Right::PROJECT_DELETE) && self::countHolders($project, $user) === 0)
            ->values()
            ->all();
    }

    /**
     * May this user see the project at all? Users who may view all projects
     * and members can.
     */
    public static function canView(User $user, Project $project): bool
    {
        return self::allows($user, Right::PROJECTS_VIEW_ALL)
            || $project->roleIdOf($user) !== null;
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

    /**
     * Refused only because the project is archived: the person sees the
     * project, and the right ends with the archive. The policies then say
     * so instead of a plain "unauthorized"; strangers learn nothing.
     */
    public static function refusedByArchive(User $user, Right $right, Project $project): bool
    {
        return ! self::withinArchive($project, $right) && self::canView($user, $project);
    }

    /**
     * An archived project allows only the rights that hold when archived
     * (Right::holdsWhenArchived), for projects.manageAny too.
     */
    private static function withinArchive(Project $project, Right $right): bool
    {
        return ! $project->isArchived() || $right->holdsWhenArchived();
    }

    /** The project cap of the user's system role allows this right (null cap = all). */
    private static function withinCap(User $user, Right $right): bool
    {
        $cap = self::grantOf($user->role_id)['cap'];

        return $cap === null || in_array($right->value, $cap, true);
    }

    /**
     * Rights and cap of one role. All roles are read in one query the first
     * time, so the number of queries does not grow with the number of roles.
     * No role, or an unknown one: no rights and a cap that allows nothing.
     *
     * @return array{rights: list<string>, cap: list<string>|null}
     */
    private static function grantOf(int|string|null $roleId): array
    {
        if ($roleId === null) {
            return ['rights' => [], 'cap' => []];
        }

        if (self::$grants === []) {
            self::loadGrants();
        }

        return self::$grants[(int) $roleId] ?? ['rights' => [], 'cap' => []];
    }

    private static function loadGrants(): void
    {
        $rows = DB::table('roles')
            ->leftJoin('role_functions', 'role_functions.role_id', '=', 'roles.id')
            ->leftJoin('function_rights', 'function_rights.function_id', '=', 'role_functions.function_id')
            ->select(['roles.id', 'roles.project_cap', 'function_rights.right'])
            ->get();

        $grants = [];
        foreach ($rows as $row) {
            $grants[$row->id] ??= [
                'rights' => [],
                'cap' => $row->project_cap === null ? null : json_decode($row->project_cap, true),
            ];
            if ($row->right !== null) {
                $grants[$row->id]['rights'][] = $row->right;
            }
        }

        foreach ($grants as $id => $grant) {
            $grants[$id]['rights'] = array_values(array_unique($grant['rights']));
        }

        self::$grants = $grants;
    }
}
