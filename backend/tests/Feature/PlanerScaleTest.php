<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\PlanerLimits;
use App\Planer\Validator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A project of the size of Club DB's history (task 29): 200 milestones with 5 todos of 5 tasks each. Export,
 * validation and the comparing import (mode full, dry run) must not need more queries per element, and they must
 * stay fast.
 */
class PlanerScaleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Project, User} */
    private function project(int $milestones): array
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $rows = [];

        for ($m = 1; $m <= $milestones; $m++) {
            $milestone = Milestone::factory()->for($project)->create(['position' => $m - 1, 'title' => "Version {$m}"]);

            for ($t = 1; $t <= 5; $t++) {
                $todo = Todo::factory()->for($milestone)->create(['position' => $t - 1, 'title' => "ToDo {$m}.{$t}"]);
                Task::factory()->count(5)->for($todo)->create();
            }
        }

        // The revision was counted by the rows made above, in the database; the model must read it.
        return [$project->refresh(), $user];
    }

    private function queries(callable $work): int
    {
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count): void {
            $count++;
        });
        $work();

        return $count;
    }

    public function test_a_project_with_the_maximum_of_milestones_exports_validates_and_compares_without_more_queries_per_element(): void
    {
        [$small, $admin] = $this->project(3);
        $smallDocument = app(Export::class)->document($small);
        $smallQueries = $this->queries(fn () => app(Export::class)->document($small));
        $smallImport = $this->queries(fn () => app(Import::class)->run($small, $smallDocument, ImportMode::Full, true, false, $admin));

        [$large] = $this->project(PlanerLimits::MILESTONES_PER_PROJECT);
        $start = microtime(true);
        $document = app(Export::class)->document($large);
        $exportSeconds = microtime(true) - $start;

        $this->assertCount(PlanerLimits::MILESTONES_PER_PROJECT, $document['milestones']);
        $this->assertSame([], Validator::errors($document), 'the file of 200 milestones is valid');

        $largeQueries = $this->queries(fn () => app(Export::class)->document($large));
        $start = microtime(true);
        $result = null;
        $largeImport = $this->queries(function () use ($large, $document, $admin, &$result): void {
            $result = app(Import::class)->run($large, $document, ImportMode::Full, true, false, $admin);
        });
        $importSeconds = microtime(true) - $start;

        $this->assertTrue($result->ok(), json_encode(array_slice($result->errors, 0, 3)));
        $this->assertSame($smallQueries, $largeQueries, 'the export needs the same number of queries for 3 and 200 milestones');
        // Measured: about 4.7 queries per task. A ceiling, so that a new query per task or per todo shows up here.
        $tasks = PlanerLimits::MILESTONES_PER_PROJECT * 5 * 5;
        $this->assertLessThan($tasks * 6, $largeImport, 'queries of the comparing import: at most 6 per task');
        $this->assertLessThan(5.0, $exportSeconds, 'export of 200 x 5 x 5');
        $this->assertLessThan(10.0, $importSeconds, 'dry run of the import (mode full) of 200 x 5 x 5');
        file_put_contents('/tmp/scale.out', sprintf("export %.2fs (%d queries), import dry run %.2fs (%d queries), small import %d queries\n", $exportSeconds, $largeQueries, $importSeconds, $largeImport, $smallImport));
    }
}
