<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\GraphQL\Errors\MembershipRefusal;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Planer\PlanerLimits;
use App\Planer\PlanerRevision;
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
 * - An agent account has the project role Agent and no other; a person never
 *   has it (Auftrag 58). Every way a membership is made or changed asks
 *   assertRoleFits(); where the role is not chosen but implied (default role
 *   of a new member), roleFor() picks the fitting one.
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
        self::assertRoleFits($user, $role);

        if (Rights::holdsProject($user, $current) && ! Rights::holdsProject($user, $role)) {
            $this->guardLastHolder($project, $user, 'role');
        }

        if ($current === null) {
            PlanerLimits::assertMemberRoom($project);
            $project->members()->attach($user, ['role_id' => $role->id]);
        } else {
            $project->members()->updateExistingPivot($user->getKey(), ['role_id' => $role->id]);
        }

        // Members are in the planer file (project.members); pivot changes fire no model events.
        app(PlanerRevision::class)->touch($project->getKey());
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
            $this->guardLastHolder($project, $user, 'userId');
        }

        $project->members()->detach($user);
        app(PlanerRevision::class)->touch($project->getKey());

        // Ground rule for documents (§4.3 rule 2): shares of the person end, a document of theirs has no owner then
        // (who has document.manageAny manages it). Versions keep the person.
        $documentIds = Document::withTrashed()->where('project_id', $project->getKey())->select('id');
        DocumentShare::query()->where('user_id', $user->getKey())->whereIn('document_id', $documentIds)->delete();
        Document::withTrashed()->where('project_id', $project->getKey())->where('owner_id', $user->getKey())->update(['owner_id' => null]);

        Task::query()
            ->inProject($project->getKey())
            ->where('assigned_user_id', $user->getKey())
            ->update(['assigned_user_id' => null]);
    }

    /**
     * The assignee of a task is a member of its project (ground rule §7.10).
     * Someone who may manage the members takes an approved person in with the
     * default project role first, in the same transaction
     * (docs/fix-m1-mitglieder.md §1.3); everyone else gets the refusal.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function ensureMember(User $actor, Project $project, int|string $userId, string $field): void
    {
        $user = User::find($userId);

        if ($user !== null && $project->roleIdOf($user) !== null) {
            return;
        }

        $role = $user === null ? null : self::roleFor($user, Role::defaultOf(Role::SCOPE_PROJECT));

        if ($user === null || $user->approved_at === null || $role === null || ! Rights::allows($actor, Right::MEMBERS_MANAGE, $project)) {
            throw ValidationException::withMessages([$field => __('projects.assignee_not_member')]);
        }

        $this->set($actor, $project, $user, $role);
    }

    /**
     * The project role Agent (shipped, cannot be deleted).
     */
    public static function agentRole(): ?Role
    {
        return Role::byKey(Role::SCOPE_PROJECT, ProjectRole::AGENT->value);
    }

    /**
     * The role for a membership whose role nobody chose (the default role): Agent for an agent account.
     */
    public static function roleFor(User $user, ?Role $implied): ?Role
    {
        return $user->isAgent() ? self::agentRole() : $implied;
    }

    /**
     * The one rule for every membership (Auftrag 58 A): an agent account only as Agent, a person never as Agent.
     *
     * @throws MembershipRefusal
     */
    public static function assertRoleFits(User $user, Role $role): void
    {
        $isAgentRole = $role->scope === Role::SCOPE_PROJECT && $role->key === ProjectRole::AGENT->value;

        if ($user->isAgent() && ! $isAgentRole) {
            throw new MembershipRefusal(MembershipRefusal::AGENT_ROLE_ONLY, (string) $user->name);
        }

        if (! $user->isAgent() && $isAgentRole) {
            throw new MembershipRefusal(MembershipRefusal::AGENT_ROLE_RESERVED, (string) $user->name);
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
    private function guardLastHolder(Project $project, User $leaving, string $field): void
    {
        // Admins too (§7.3): every project keeps a member who may delete it.
        if (Rights::countHolders($project, $leaving) === 0) {
            throw ValidationException::withMessages([$field => __('projects.last_owner')]);
        }
    }
}
