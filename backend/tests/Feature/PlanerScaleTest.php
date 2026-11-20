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
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * A project of the size of Club DB's history (task 29): 200 milestones with 5 todos of 5 tasks each. Export and the
 * comparing import (mode full, dry run) need a number of queries that does not grow with the size (no N+1). The CI
 * counts queries, never seconds (shared runners are slow and uneven); the times are measured by the test of the group
 * "benchmark", which the CI leaves out: php artisan test --group=benchmark, the result is in the file named below.
 */
class PlanerScaleTest extends TestCase
{
    use RefreshDatabase;

    public const BENCHMARK_FILE = '/tmp/planer-scale-benchmark.txt';

    /** @return array{Project, User} */
    private function project(int $milestones): array
    {
        $user = User::factory()->admin()->create();
        $project = Project::factory()->create();

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

    public function test_export_and_the_comparing_import_need_as_many_queries_for_200_milestones_as_for_3(): void
    {
        [$small, $admin] = $this->project(3);
        $smallDocument = app(Export::class)->document($small);
        $smallExport = $this->queries(fn () => app(Export::class)->document($small));
        $smallImport = $this->queries(fn () => app(Import::class)->run($small, $smallDocument, ImportMode::Full, true, false, $admin));

        [$large] = $this->project(PlanerLimits::MILESTONES_PER_PROJECT);
        $document = app(Export::class)->document($large);

        $this->assertCount(PlanerLimits::MILESTONES_PER_PROJECT, $document['milestones']);
        $this->assertSame([], Validator::errors($document), 'the file of 200 milestones is valid');

        $largeExport = $this->queries(fn () => app(Export::class)->document($large));
        $result = null;
        $largeImport = $this->queries(function () use ($large, $document, $admin, &$result): void {
            $result = app(Import::class)->run($large, $document, ImportMode::Full, true, false, $admin);
        });

        $this->assertTrue($result->ok(), json_encode(array_slice($result->errors, 0, 3)));
        $this->assertSame($smallExport, $largeExport, 'export: the same queries for 3 and for 200 milestones');
        $this->assertLessThanOrEqual($smallImport + 30, $largeImport, 'the comparing import: a small, fixed number more, not one per element');
    }

    #[Group('benchmark')]
    public function test_benchmark_the_times_for_200_x_5_x_5(): void
    {
        [$large, $admin] = $this->project(PlanerLimits::MILESTONES_PER_PROJECT);

        $start = microtime(true);
        $document = app(Export::class)->document($large);
        $export = microtime(true) - $start;

        $start = microtime(true);
        app(Import::class)->run($large, $document, ImportMode::Full, true, false, $admin);
        $import = microtime(true) - $start;

        file_put_contents(self::BENCHMARK_FILE, sprintf("%s: export %.2f s, import (full, dry run) %.2f s, 200 x 5 x 5 on %s\n", date('c'), $export, $import, DB::getDriverName()), FILE_APPEND);
        $this->assertTrue(true);
    }
}
