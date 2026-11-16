<?php

namespace Tests\Feature\GraphQL;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Assigning a task to someone who is not a member yet
 * (docs/fix-m1-mitglieder.md §1.3): with members.manage the person joins
 * with the default project role, in the same change.
 */
class AssignNonMemberTest extends GraphQLTestCase
{
    private const ASSIGN = 'mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { assignee { name } } }';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function revision(): int
    {
        return (int) DB::table('projects')->where('id', $this->project->id)->value('planer_revision');
    }

    public function test_a_manager_takes_the_person_in_with_the_default_role_and_assigns_in_one_change(): void
    {
        $before = $this->revision();

        $this->as('manager')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['stranger']->id])
            ->assertJsonPath('data.assignTask.assignee.name', 'Eva Stranger');

        $this->assertSame(Role::defaultOf(Role::SCOPE_PROJECT)?->key, $this->project->fresh()->roleOf($this->users['stranger'])?->key);
        $this->assertSame($before + 1, $this->revision());
    }

    public function test_creating_a_task_takes_the_assignee_in_as_well(): void
    {
        $this->as('manager')->gql('mutation ($t: ID!, $u: ID!) { createTask(input: {todoId: $t, title: "Neu", assigneeId: $u}) { assignee { name } } }', [
            't' => $this->todo->id, 'u' => $this->users['stranger']->id,
        ])->assertJsonPath('data.createTask.assignee.name', 'Eva Stranger');

        $this->assertNotNull($this->project->fresh()->roleOf($this->users['stranger']));
    }

    public function test_without_members_manage_only_members_can_be_assigned(): void
    {
        // May assign tasks, may not manage members.
        $dispatcher = Role::create(['scope' => Role::SCOPE_PROJECT, 'key' => 'dispatcher', 'names' => ['de' => 'Disposition', 'en' => 'Dispatcher']]);
        foreach (DB::table('functions')->where('scope', Role::SCOPE_PROJECT)->whereIn('key', ['plan', 'contribute', 'manage-tasks'])->pluck('id') as $functionId) {
            DB::table('role_functions')->insert(['role_id' => $dispatcher->id, 'function_id' => $functionId]);
        }
        Rights::clearCache();
        $this->project->members()->updateExistingPivot($this->users['member']->id, ['role_id' => $dispatcher->id]);

        $this->as('member')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['stranger']->id])
            ->assertGraphQLValidationKeys(['assigneeId']);

        $this->assertNull($this->project->fresh()->roleOf($this->users['stranger']));
        $this->assertNull(Task::find($this->task->id)->assigned_user_id);
    }

    public function test_a_person_who_is_not_approved_cannot_be_taken_in(): void
    {
        $pending = User::factory()->unapproved()->create();

        $this->as('manager')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $pending->id])
            ->assertGraphQLValidationKeys(['assigneeId']);
    }

    public function test_a_member_creates_tasks_without_assignee_or_for_themselves_but_not_for_others(): void
    {
        $create = 'mutation ($t: ID!, $u: ID) { createTask(input: {todoId: $t, title: "Neu", assigneeId: $u}) { assignee { name } } }';

        $this->as('member')->gql($create, ['t' => $this->todo->id, 'u' => null])
            ->assertJsonPath('data.createTask.assignee', null);
        $this->as('member')->gql($create, ['t' => $this->todo->id, 'u' => $this->users['member']->id])
            ->assertJsonPath('data.createTask.assignee.name', 'David Member');
        $this->assertUnauthorized($this->as('member')->gql($create, ['t' => $this->todo->id, 'u' => $this->users['manager']->id]));
    }

    public function test_assigning_oneself_is_unchanged(): void
    {
        $this->as('member')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['member']->id])
            ->assertJsonPath('data.assignTask.assignee.name', 'David Member');

        $this->assertUnauthorized($this->as('member')->gql(self::ASSIGN, ['id' => $this->task->id, 'u' => $this->users['stranger']->id]));
    }
}
