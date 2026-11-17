<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Support\DevelopmentHistory\HistoryMarkdown;
use Database\Seeders\DevelopmentHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_project_from_the_history_file_once(): void
    {
        $expected = HistoryMarkdown::parse((string) file_get_contents(base_path('../docs/entwicklung.md')));
        $taskCount = collect($expected['milestones'])->sum(fn (array $m): int => collect($m['todos'])->sum(fn (array $t): int => count($t['tasks'])));

        $this->seed(DevelopmentHistorySeeder::class);
        $this->seed(DevelopmentHistorySeeder::class);

        $project = Project::query()->where('name', DevelopmentHistorySeeder::PROJECT_NAME)->sole();
        $this->assertSame(['research', 'design', 'feature', 'bug', 'planning', 'blocked'], $project->labels()->pluck('key')->all());
        $this->assertSame(count($expected['milestones']), Milestone::query()->where('project_id', $project->id)->count());
        $this->assertSame($taskCount, Task::query()->count());
    }

    public function test_done_tasks_are_done_and_the_kind_is_a_label(): void
    {
        $this->seed(DevelopmentHistorySeeder::class);

        $this->assertSame(TaskStatus::DONE, Task::query()->where('title', 'Add Laravel backend, docs and migrations (step 1)')->firstOrFail()->status);

        $planning = Task::query()->where('title', 'Add confirmed frontend plan')->firstOrFail();
        $this->assertSame(['planning'], $planning->labels()->pluck('labels.key')->all());
        $this->assertSame(['Release-Prozess über GitHub Actions für macOS und Windows'], Task::query()->where('title', 'like', 'Release-Prozess%')->pluck('title')->all());
    }

    public function test_an_invalid_history_stops_the_seed_before_anything_is_created(): void
    {
        $path = base_path('../docs/entwicklung.md');
        $original = (string) file_get_contents($path);
        file_put_contents($path, $original."\n## v0.0 Kaputt\n");

        try {
            $this->expectExceptionMessage('docs/entwicklung.md is invalid');
            $this->seed(DevelopmentHistorySeeder::class);
        } finally {
            file_put_contents($path, $original);
            $this->assertFalse(Project::query()->where('name', DevelopmentHistorySeeder::PROJECT_NAME)->exists());
        }
    }

    public function test_the_project_has_the_planning_label_of_the_template(): void
    {
        $this->seed(DevelopmentHistorySeeder::class);

        $this->assertTrue(Label::query()->where('key', 'planning')->exists());
    }
}
