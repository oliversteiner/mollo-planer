<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Task.viewerCan* (docs/fix-m1-f4.md F4.5): the same answers as the
 * TaskPolicy, per task, because task.updateOwn depends on who created it.
 */
class TaskViewerCanTest extends GraphQLTestCase
{
    private const QUERY = 'query ($id: ID!) { task(id: $id) { viewerCanUpdate viewerCanAssign viewerCanAssignSelf viewerCanDelete } }';

    /**
     * @return array{viewerCanUpdate: bool, viewerCanAssign: bool, viewerCanAssignSelf: bool, viewerCanDelete: bool}
     */
    private function can(string $who, Task $task): array
    {
        return $this->as($who)->gql(self::QUERY, ['id' => $task->id])->json('data.task');
    }

    private function taskBy(string $who): Task
    {
        $this->actingAs($this->users[$who]);

        return Task::factory()->for($this->todo)->create();
    }

    public function test_an_owner_may_do_everything_with_any_task(): void
    {
        $this->assertSame(
            ['viewerCanUpdate' => true, 'viewerCanAssign' => true, 'viewerCanAssignSelf' => true, 'viewerCanDelete' => true],
            $this->can('owner', $this->taskBy('manager')),
        );
    }

    public function test_a_member_changes_their_own_task_and_assigns_only_themselves(): void
    {
        $this->assertSame(
            ['viewerCanUpdate' => true, 'viewerCanAssign' => false, 'viewerCanAssignSelf' => true, 'viewerCanDelete' => false],
            $this->can('member', $this->taskBy('member')),
        );
    }

    public function test_a_member_may_not_change_someone_elses_task(): void
    {
        $this->assertFalse($this->can('member', $this->taskBy('manager'))['viewerCanUpdate']);
    }

    public function test_a_guest_and_a_read_only_person_may_do_nothing(): void
    {
        $this->project->members()->attach($this->users['stranger']->id, ['role_id' => $this->projectRole(ProjectRole::GUEST)?->id]);
        $this->project->members()->attach($this->users['lesend']->id, ['role_id' => $this->projectRole(ProjectRole::OWNER)?->id]);
        $nothing = ['viewerCanUpdate' => false, 'viewerCanAssign' => false, 'viewerCanAssignSelf' => false, 'viewerCanDelete' => false];

        $this->assertSame($nothing, $this->can('stranger', $this->taskBy('stranger')));
        $this->assertSame($nothing, $this->can('lesend', $this->taskBy('lesend')));
    }

    public function test_the_answers_agree_with_the_mutation(): void
    {
        $own = $this->taskBy('member');
        $other = $this->taskBy('manager');
        $update = 'mutation ($id: ID!) { updateTask(id: $id, input: {title: "Neu"}) { title } }';

        $this->as('member')->gql($update, ['id' => $own->id])->assertJsonPath('data.updateTask.title', 'Neu');
        $this->assertUnauthorized($this->as('member')->gql($update, ['id' => $other->id]));
    }

    public function test_a_board_full_of_tasks_costs_no_query_per_task(): void
    {
        $query = 'query ($id: ID!) { project(id: $id) { milestones { todos { tasks { viewerCanUpdate viewerCanAssign viewerCanAssignSelf viewerCanDelete } } } } }';
        $count = function () use ($query): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->as('member')->gql($query, ['id' => $this->project->id])->assertJsonMissingPath('errors');

            return count(DB::getQueryLog());
        };

        $this->taskBy('member');
        $count(); // reads the rights of the roles once per process
        $few = $count();
        $this->actingAs($this->users['member']);
        Task::factory()->count(10)->for($this->todo)->create();
        $many = $count();

        $this->assertSame($few, $many);
    }
}
