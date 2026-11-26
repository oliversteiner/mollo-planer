<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\GraphQL\Errors\MembershipRefusal;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SecurityChange;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Services\RoleAdministration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Auftrag 58: an agent account is in every project only in the project role Agent, a person never; on every way a
 * membership is made or changed. The migration puts old memberships right, and no mail ever goes to an agent.
 */
class AgentProjectRoleTest extends GraphQLTestCase
{
    private const SET = 'mutation ($p: ID!, $u: ID!, $r: String!) { setProjectMember(projectId: $p, userId: $u, role: $r) { id } }';

    private const CREATE = 'mutation ($in: CreateProjectInput!) { createProject(input: $in) { id } }';

    private const ASSIGN = 'mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { assignee { name } } }';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create(['name' => 'Kevin', 'email' => 'kevin@agent.invalid']);
    }

    private function roleKeyOf(User $user): ?string
    {
        return Role::cached($this->project->fresh()->roleIdOf($user))?->key;
    }

    public function test_set_project_member_takes_an_agent_only_as_agent(): void
    {
        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->agent->id, 'r' => 'member'])
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_ONLY)
            ->assertJsonPath('errors.0.extensions.refusal', true)
            ->assertJsonPath('errors.0.message', __('projects.membership_refusal.AGENT_ROLE_ONLY', ['name' => 'Kevin']));
        $this->assertNull($this->roleKeyOf($this->agent));

        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->agent->id, 'r' => 'agent'])->assertJsonMissingPath('errors');
        $this->assertSame('agent', $this->roleKeyOf($this->agent));

        // Nor can the role of an agent member be changed afterwards.
        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->agent->id, 'r' => 'manager'])
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_ONLY);
        $this->assertSame('agent', $this->roleKeyOf($this->agent));
    }

    public function test_set_project_member_never_gives_a_person_the_role_agent(): void
    {
        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->users['stranger']->id, 'r' => 'agent'])
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_RESERVED);
        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->users['member']->id, 'r' => 'agent'])
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_RESERVED);

        $this->assertNull($this->roleKeyOf($this->users['stranger']));
        $this->assertSame('member', $this->roleKeyOf($this->users['member']));
    }

    public function test_create_project_with_members_follows_the_rule_both_ways(): void
    {
        $input = fn (string $userId, string $role): array => ['in' => ['name' => 'Neu', 'members' => [['userId' => $userId, 'role' => $role]]]];

        $this->as('admin')->gql(self::CREATE, $input((string) $this->agent->id, 'member'))
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_ONLY);
        $this->as('admin')->gql(self::CREATE, $input((string) $this->users['member']->id, 'agent'))
            ->assertJsonPath('errors.0.extensions.code', MembershipRefusal::AGENT_ROLE_RESERVED);
        $this->assertSame(0, DB::table('projects')->where('name', 'Neu')->count(), 'nothing was made');

        $this->as('admin')->gql(self::CREATE, $input((string) $this->agent->id, 'agent'))->assertJsonMissingPath('errors');
    }

    public function test_assigning_a_task_to_an_agent_takes_it_in_as_agent(): void
    {
        $this->as('owner')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->agent->id])
            ->assertJsonPath('data.assignTask.assignee.name', 'Kevin');

        $this->assertSame('agent', $this->roleKeyOf($this->agent));
    }

    public function test_the_import_refuses_both_cases_and_writes_nothing(): void
    {
        $this->as('owner')->gql(self::SET, ['p' => $this->project->id, 'u' => $this->agent->id, 'r' => 'agent']);
        $document = app(Export::class)->document($this->project->fresh());

        foreach ($document['project']['members'] as $index => $member) {
            $document['project']['members'][$index]['role'] = match ($member['email']) {
                $this->agent->email => 'member',
                $this->users['member']->email => 'agent',
                default => $member['role'],
            };
        }

        $result = app(Import::class)->run($this->project, $document, ImportMode::Partial, false, false, $this->users['owner']);

        $this->assertFalse($result->ok());
        $messages = array_column($result->errors, 'message');
        $this->assertContains(__('projects.membership_refusal.AGENT_ROLE_ONLY', ['name' => 'Kevin']), $messages);
        $this->assertContains(__('projects.membership_refusal.AGENT_ROLE_RESERVED', ['name' => $this->users['member']->name]), $messages);
        $this->assertSame('agent', $this->roleKeyOf($this->agent));
        $this->assertSame('member', $this->roleKeyOf($this->users['member']));
    }

    public function test_an_agent_cannot_create_a_project_by_import_since_it_would_get_the_creator_role(): void
    {
        $this->agent->forceFill(['role_id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::PROJEKTLEITUNG)])->save();
        $document = app(Export::class)->document($this->project->fresh());
        $document['project']['name'] = 'Vom Agenten';

        $result = app(Import::class)->run(null, $document, ImportMode::Full, false, false, $this->agent);

        $this->assertFalse($result->ok());
        $this->assertSame('project', $result->errors[0]['path']);
        $this->assertSame(0, DB::table('projects')->where('name', 'Vom Agenten')->count());
    }

    public function test_the_role_agent_cannot_become_the_default_project_role(): void
    {
        $this->expectException(ValidationException::class);

        app(RoleAdministration::class)->setDefaultRole($this->users['admin'], Role::idOf(Role::SCOPE_PROJECT, ProjectRole::AGENT));
    }

    #[Group('mariadb')]
    public function test_the_migration_puts_old_memberships_right(): void
    {
        $agentRole = Role::idOf(Role::SCOPE_PROJECT, ProjectRole::AGENT);
        $memberRole = Role::idOf(Role::SCOPE_PROJECT, ProjectRole::MEMBER);
        $this->project->members()->attach($this->agent->id, ['role_id' => $memberRole]);
        $this->project->members()->updateExistingPivot($this->users['member']->id, ['role_id' => $agentRole]);

        $migration = require database_path('migrations/2026_11_26_100000_fix_agent_project_roles.php');
        $migration->up();

        $this->assertSame('agent', $this->roleKeyOf($this->agent));
        $this->assertSame(Role::defaultOf(Role::SCOPE_PROJECT)->key, $this->roleKeyOf($this->users['member']));
        $this->assertSame('owner', $this->roleKeyOf($this->users['owner']), 'others stay');
    }

    public function test_no_mail_goes_to_an_agent_whatever_the_notification(): void
    {
        $this->task->update(['assigned_user_id' => $this->agent->id]);
        $notifications = [
            new TaskAssigned($this->task, 'Ben Owner'),
            new TaskCommented($this->task, 'Ben Owner'),
            new SecurityChange(SecurityChange::PASSWORD),
        ];
        $this->project->members()->attach($this->agent->id, ['role_id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::AGENT)]);

        foreach ($notifications as $notification) {
            $this->agent->notifyNow($notification);
        }
        $this->assertCount(0, $this->sentMails(), 'nothing to the agent');

        // The same notifications reach a person.
        $this->users['member']->notifyNow(new SecurityChange(SecurityChange::PASSWORD));
        $this->assertCount(1, $this->sentMails());
    }

    /**
     * @return iterable<mixed>
     */
    private function sentMails(): iterable
    {
        return $this->app['mailer']->getSymfonyTransport()->messages();
    }
}
