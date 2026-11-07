<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusObserverTest extends TestCase
{
    use RefreshDatabase;

    private Milestone $milestone;

    private Todo $todo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestone = Milestone::factory()->create();
        $this->todo = Todo::factory()->for($this->milestone)->create();
    }

    public function test_new_items_without_children_are_open(): void
    {
        $this->assertStatus('open', $this->todo);
        $this->assertStatus('open', $this->milestone);
        $this->assertStatus('open', Milestone::factory()->create());
    }

    public function test_todo_and_milestone_are_done_when_all_tasks_are_done(): void
    {
        $first = $this->task();
        $second = $this->task();

        $this->setTaskStatus($first, 'done');
        $this->assertStatus('open', $this->todo);

        $this->setTaskStatus($second, 'done');
        $this->assertStatus('done', $this->todo);
        $this->assertStatus('done', $this->milestone);
    }

    public function test_new_task_reopens_todo_and_milestone(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $this->assertStatus('done', $this->milestone);

        $this->task();

        $this->assertStatus('open', $this->todo);
        $this->assertStatus('open', $this->milestone);
    }

    public function test_resetting_a_task_reopens_the_todo(): void
    {
        $task = $this->task();
        $this->setTaskStatus($task, 'done');

        $this->setTaskStatus($task, 'review');

        $this->assertStatus('open', $this->todo);
        $this->assertStatus('open', $this->milestone);
    }

    public function test_moving_between_unfinished_columns_keeps_todo_open(): void
    {
        $task = $this->task();

        $this->setTaskStatus($task, 'in_progress');
        $this->setTaskStatus($task, 'review');

        $this->assertStatus('open', $this->todo);
    }

    public function test_deleting_the_last_unfinished_task_completes_the_todo(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $open = $this->task();

        $open->delete();

        $this->assertStatus('done', $this->todo);
        $this->assertStatus('done', $this->milestone);
    }

    public function test_deleting_all_tasks_reopens_the_todo(): void
    {
        $task = $this->task();
        $this->setTaskStatus($task, 'done');

        $task->delete();

        $this->assertStatus('open', $this->todo);
        $this->assertStatus('open', $this->milestone);
    }

    public function test_new_todo_reopens_a_done_milestone(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $this->assertStatus('done', $this->milestone);

        Todo::factory()->for($this->milestone)->create();

        $this->assertStatus('open', $this->milestone);
    }

    public function test_deleting_the_last_open_todo_completes_the_milestone(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $openTodo = Todo::factory()->for($this->milestone)->create();
        Task::factory()->for($openTodo)->create();
        $this->assertStatus('open', $this->milestone);

        $openTodo->delete();

        $this->assertStatus('done', $this->milestone);
    }

    public function test_moving_a_task_updates_both_todos(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $open = $this->task();
        $otherTodo = Todo::factory()->create();

        $open->todo_id = $otherTodo->id;
        $open->save();

        $this->assertStatus('done', $this->todo);
        $this->assertStatus('open', $otherTodo);
    }

    public function test_moving_a_todo_updates_both_milestones(): void
    {
        $this->setTaskStatus($this->task(), 'done');
        $openTodo = Todo::factory()->for($this->milestone)->create();
        $otherMilestone = Milestone::factory()->create();

        $openTodo->milestone_id = $otherMilestone->id;
        $openTodo->save();

        $this->assertStatus('done', $this->milestone);
        $this->assertStatus('open', $otherMilestone);
    }

    public function test_status_cannot_be_set_by_input(): void
    {
        $todo = Todo::create(['milestone_id' => $this->milestone->id, 'title' => 'T', 'status' => 'done']);
        $this->assertStatus('open', $todo);

        $todo->update(['status' => 'done']);
        $this->milestone->update(['status' => 'done']);

        $this->assertStatus('open', $todo);
        $this->assertStatus('open', $this->milestone);
    }

    private function task(): Task
    {
        return Task::factory()->for($this->todo)->create();
    }

    private function setTaskStatus(Task $task, string $status): void
    {
        $task->status = $status;
        $task->save();
    }

    private function assertStatus(string $expected, Todo|Milestone $item): void
    {
        $this->assertSame($expected, $item->fresh()->status, class_basename($item)." {$item->id}");
    }
}
