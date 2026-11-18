<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * todos.project_id and tasks.project_id follow their parent (schema-review A1).
 * Input never sets them.
 */
class ProjectIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_todo_and_a_task_take_the_project_of_their_parent(): void
    {
        $project = Project::factory()->create();
        $milestone = Milestone::factory()->for($project)->create();
        $todo = Todo::factory()->for($milestone)->create();
        $task = Task::factory()->for($todo)->create();

        $this->assertSame($project->getKey(), $todo->fresh()->project_id);
        $this->assertSame($project->getKey(), $task->fresh()->project_id);
    }

    public function test_a_project_id_in_the_input_is_ignored(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        $todo = Todo::factory()->for(Milestone::factory()->for($project)->create())->create();

        $task = new Task(['title' => 'Eingeschmuggelt', 'project_id' => $other->getKey()]);
        $task->todo()->associate($todo);
        $task->save();

        $this->assertSame($project->getKey(), $task->fresh()->project_id);
    }

    public function test_moving_a_todo_to_another_milestone_of_the_project_keeps_the_project(): void
    {
        $project = Project::factory()->create();
        $first = Milestone::factory()->for($project)->create();
        $second = Milestone::factory()->for($project)->create();
        $todo = Todo::factory()->for($first)->create();
        $task = Task::factory()->for($todo)->create();

        $todo->update(['milestone_id' => $second->getKey()]);

        $this->assertSame($project->getKey(), $todo->fresh()->project_id);
        $this->assertSame($project->getKey(), $task->fresh()->project_id);
    }

    public function test_the_project_scope_finds_the_tasks_of_one_project_only(): void
    {
        $mine = Project::factory()->create();
        $theirs = Project::factory()->create();
        $myTask = Task::factory()->for(Todo::factory()->for(Milestone::factory()->for($mine)->create())->create())->create();
        Task::factory()->for(Todo::factory()->for(Milestone::factory()->for($theirs)->create())->create())->create();

        $this->assertSame([$myTask->getKey()], Task::query()->inProject($mine->getKey())->pluck('id')->all());
    }
}
