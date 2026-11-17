<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\PlanerLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckLimitsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_inside_the_limits_passes(): void
    {
        $todo = Todo::factory()->for(Milestone::factory()->for(Project::factory()))->create();
        Task::factory()->for($todo)->create();

        $this->artisan('planer:check-limits')->assertSuccessful();
    }

    public function test_every_place_over_a_limit_is_listed_and_nothing_changes(): void
    {
        $project = Project::factory()->create(['name' => 'Zu gross']);
        $milestone = Milestone::factory()->for($project)->create();
        $todo = Todo::factory()->for($milestone)->create();
        Task::withoutEvents(fn () => Task::factory()->count(PlanerLimits::TASKS_PER_TODO + 1)->for($todo)->create(['project_id' => $project->id]));
        $long = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', 5001)]]]]];
        $milestone->description = $long;
        $milestone->saveQuietly();
        $before = Task::query()->count();

        $this->artisan('planer:check-limits')
            ->expectsOutputToContain('Zu gross')
            ->expectsOutputToContain('milestones[0].description')
            ->expectsOutputToContain('milestones[0].todos[0]')
            ->assertFailed();

        $this->assertSame($before, Task::query()->count());
    }
}
