<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ItemStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Notification;

/**
 * Status idea and planned (planer-abgleich §8.2): ideas do not count for the
 * status or progress of their todo, and the assignment mail waits until the
 * task leaves idea.
 */
class IdeaStatusTest extends GraphQLTestCase
{
    public function test_an_idea_does_not_count_for_the_status_of_its_todo(): void
    {
        $this->task->forceFill(['status' => TaskStatus::IDEA])->save();
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);

        $this->assertSame(ItemStatus::DONE, $this->todo->fresh()->status);
    }

    public function test_planned_counts_like_open_for_the_status_of_its_todo(): void
    {
        $this->task->forceFill(['status' => TaskStatus::PLANNED])->save();
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);

        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);
    }

    public function test_a_todo_with_only_ideas_is_open(): void
    {
        $this->task->forceFill(['status' => TaskStatus::IDEA])->save();

        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);
    }

    public function test_progress_leaves_ideas_out(): void
    {
        $this->task->forceFill(['status' => TaskStatus::IDEA])->save();
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);

        $this->as('member')->gql('query ($id: ID!) { project(id: $id) { progress { total done percent } } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.progress', ['total' => 1, 'done' => 1, 'percent' => 100]);
    }

    public function test_my_open_tasks_leave_ideas_out(): void
    {
        $this->task->forceFill(['status' => TaskStatus::IDEA, 'assigned_user_id' => $this->users['member']->id])->save();

        $this->as('member')->gql('{ dashboard { myOpenTasks { id } } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.dashboard.myOpenTasks', []);
    }

    public function test_assignment_mail_waits_until_the_task_leaves_idea(): void
    {
        Notification::fake();
        $this->task->forceFill(['status' => TaskStatus::IDEA])->save();

        $this->as('manager')->gql('mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { id } }', [
            'id' => $this->task->id, 'u' => $this->users['member']->id,
        ])->assertJsonMissingPath('errors');
        Notification::assertNothingSent();

        $this->as('manager')->gql('mutation ($id: ID!) { moveTask(id: $id, status: PLANNED, position: 0) { status } }', [
            'id' => $this->task->id,
        ])->assertJsonPath('data.moveTask.status', 'PLANNED');
        Notification::assertSentTo($this->users['member'], TaskAssigned::class);
    }
}
