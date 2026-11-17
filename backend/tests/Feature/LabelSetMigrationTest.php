<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration of step S7 on data that exists: every project gets copies of
 * the labels of its set (and of the labels its tasks carry from other sets),
 * the tasks keep their labels, the old tables and rights are gone.
 *
 * Builds the database as it was before the migration, runs it, and builds the
 * current database again at the end.
 */
class LabelSetMigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_17_120000_replace_label_sets_by_templates_and_project_labels';

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh');

        parent::tearDown();
    }

    public function test_existing_label_sets_become_labels_of_the_projects(): void
    {
        $before = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $file): string => basename($file, '.php'))
            ->filter(fn (string $name): bool => $name < self::MIGRATION)
            ->sort()->map(fn (string $name): string => "database/migrations/{$name}.php")->values()->all();

        Artisan::call('migrate:fresh', ['--path' => $before]);
        $this->assertTrue(Schema::hasTable('label_sets'));

        $now = now();
        $software = DB::table('label_sets')->insertGetId(['key' => 'software_dev', 'name' => 'Softwareentwicklung', 'created_at' => $now, 'updated_at' => $now]);
        $own = DB::table('label_sets')->insertGetId(['key' => null, 'name' => 'Eigenes Set', 'created_at' => $now, 'updated_at' => $now]);
        $label = fn (int $set, ?string $key, string $name, string $category, string $color, int $position): int => DB::table('labels')->insertGetId([
            'label_set_id' => $set, 'key' => $key, 'name' => $name, 'category' => $category, 'color' => $color, 'position' => $position, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $research = $label($software, 'research', 'Recherche', 'phase', 'blue', 0);
        $bug = $label($software, 'bug', 'Fehler', 'type', 'red', 1);
        $ui = $label($own, null, 'Über UI', 'Bereich', 'green', 0);
        $ui2 = $label($own, null, 'Über-UI', 'Bereich', 'pink', 1);

        $withSet = $this->project('Mit Set', 'de', $software);
        $withOwn = $this->project('Eigene Labels', 'en', $own);
        $withoutSet = $this->project('Ohne Set', 'de', null);

        $attach = function (Project $project, int $labelId): void {
            // Without events: the observer writes columns a later migration adds.
            $todo = Todo::factory()->for(Milestone::factory()->for($project))->create();
            $task = Task::withoutEvents(fn () => Task::factory()->for($todo)->create(['project_id' => $project->id]));
            DB::table('label_task')->insert(['label_id' => $labelId, 'task_id' => $task->id]);
        };
        $attach($withSet, $bug);
        $attach($withOwn, $ui2);
        $attach($withoutSet, $research); // carried from a set the project does not use

        $functionId = DB::table('functions')->where('key', 'templates')->value('id');
        $this->assertNotNull($functionId);
        DB::table('function_rights')->insertOrIgnore([['function_id' => $functionId, 'right' => 'labelSets.manage']]);

        Artisan::call('migrate', ['--path' => 'database/migrations/'.self::MIGRATION.'.php']);

        $this->assertFalse(Schema::hasTable('label_sets'));
        $this->assertFalse(Schema::hasColumn('projects', 'label_set_id'));
        $this->assertTrue(Schema::hasTable('templates'));
        $this->assertTrue(Schema::hasColumn('projects', 'template_id'));
        $this->assertSame(0, DB::table('function_rights')->where('right', 'like', 'labelSets.%')->count());

        $labels = fn (Project $project): array => Label::query()->where('project_id', $project->id)->orderBy('position')->get()
            ->map(fn (Label $copy): array => [$copy->key, $copy->names, $copy->category, $copy->color->value, $copy->position])->all();

        $this->assertSame([
            ['research', ['de' => 'Recherche', 'en' => 'Research'], 'phase', 'blue', 0],
            ['bug', ['de' => 'Fehler', 'en' => 'Bug'], 'type', 'red', 1],
        ], $labels($withSet));
        $this->assertSame([
            ['uber_ui', ['en' => 'Über UI'], 'Bereich', 'green', 0],
            ['uber_ui_2', ['en' => 'Über-UI'], 'Bereich', 'pink', 1],
        ], $labels($withOwn));
        $this->assertSame([['research', ['de' => 'Recherche', 'en' => 'Research'], 'phase', 'blue', 0]], $labels($withoutSet));

        // Each task has the copy of its own project.
        $keysOfTasks = DB::table('label_task')
            ->join('labels', 'labels.id', '=', 'label_task.label_id')
            ->join('tasks', 'tasks.id', '=', 'label_task.task_id')
            ->orderBy('tasks.project_id')->get(['tasks.project_id', 'labels.project_id as label_project', 'labels.key'])
            ->map(fn (object $row): array => [(int) $row->project_id === (int) $row->label_project, $row->key])->all();
        $this->assertSame([[true, 'bug'], [true, 'uber_ui_2'], [true, 'research']], $keysOfTasks);
        $this->assertSame(3, DB::table('label_task')->count());
        $this->assertNotSame($ui, $ui2);
    }

    private function project(string $name, string $locale, ?int $labelSetId): Project
    {
        $project = Project::factory()->create(['name' => $name, 'locale' => $locale]);
        DB::table('projects')->where('id', $project->id)->update(['label_set_id' => $labelSetId]);

        return $project;
    }
}
