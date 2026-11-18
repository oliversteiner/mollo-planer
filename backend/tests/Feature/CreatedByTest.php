<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * created_by is the logged-in user who created the row (schema-review A2).
 */
class CreatedByTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_logged_in_user_is_recorded_on_create(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $milestone = Milestone::factory()->for(Project::factory()->create())->create();
        $todo = Todo::factory()->for($milestone)->create();
        $task = Task::factory()->for($todo)->create();

        $this->assertSame($user->getKey(), $milestone->fresh()->created_by);
        $this->assertSame($user->getKey(), $todo->fresh()->created_by);
        $this->assertSame($user->getKey(), $task->fresh()->created_by);
        $this->assertSame($user->getKey(), $task->createdBy->getKey());
    }

    public function test_without_a_login_it_stays_empty_and_input_is_ignored(): void
    {
        $other = User::factory()->create();
        $milestone = Milestone::factory()->for(Project::factory()->create())->create();

        $todo = new Todo(['title' => 'Ohne Login', 'created_by' => $other->getKey()]);
        $todo->milestone()->associate($milestone);
        $todo->save();

        $this->assertNull($todo->fresh()->created_by);
    }

    public function test_deleting_the_creator_keeps_the_row(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $task = Task::factory()->for(Todo::factory()->for(Milestone::factory()->for(Project::factory()->create())->create())->create())->create();

        $user->delete();

        $this->assertNotNull(Task::query()->find($task->getKey()));
        $this->assertNull($task->fresh()->created_by);
    }
}
