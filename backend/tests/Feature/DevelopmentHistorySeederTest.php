<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planer\Export;
use App\Planer\Validator;
use Database\Seeders\DevelopmentHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevelopmentHistorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_project_from_the_planer_file_once_and_its_export_is_the_file(): void
    {
        User::factory()->admin()->create();
        $file = json_decode((string) file_get_contents(base_path('../docs/entwicklung.planer.json')), true, 512, JSON_THROW_ON_ERROR);
        $taskCount = collect($file['milestones'])->sum(fn (array $m): int => collect($m['todos'])->sum(fn (array $t): int => count($t['tasks'])));

        $this->seed(DevelopmentHistorySeeder::class);
        $this->seed(DevelopmentHistorySeeder::class);

        $project = Project::query()->where('name', DevelopmentHistorySeeder::PROJECT_NAME)->sole();
        $this->assertSame($taskCount, Task::query()->count());
        $this->assertSame(['research', 'design', 'feature', 'bug', 'planning', 'blocked'], $project->labels()->pluck('key')->all());

        $exported = app(Export::class)->document($project);
        $this->assertSame(array_column($file['milestones'], 'title'), array_column($exported['milestones'], 'title'));
        $this->assertSame(
            collect($file['milestones'])->flatMap(fn (array $m) => collect($m['todos'])->flatMap(fn (array $t) => array_column($t['tasks'], 'title')))->all(),
            collect($exported['milestones'])->flatMap(fn (array $m) => collect($m['todos'])->flatMap(fn (array $t) => array_column($t['tasks'], 'title')))->all(),
        );
    }

    public function test_without_an_admin_it_says_so_and_creates_nothing(): void
    {
        $this->expectExceptionMessage('There is no admin');

        try {
            $this->seed(DevelopmentHistorySeeder::class);
        } finally {
            $this->assertFalse(Project::query()->exists());
        }
    }

    public function test_the_file_is_valid(): void
    {
        $file = json_decode((string) file_get_contents(base_path('../docs/entwicklung.planer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], Validator::errors($file));
    }
}
