<?php

namespace Tests\Feature;

use App\Enums\ReleaseStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** release.date (task 29, docs/spec format-v2.md §2.3): always optional, follows the status in the app. */
class ReleaseDateTest extends TestCase
{
    use RefreshDatabase;

    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestone = Milestone::factory()->for(Project::factory()->create())->create();
        $this->milestone->update(['release_version' => '1.4', 'release_status' => ReleaseStatus::PLANNED]);
    }

    public function test_becoming_released_without_a_date_sets_today(): void
    {
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED]);

        $this->assertSame(now()->format('Y-m-d'), $this->milestone->fresh()->release_date->format('Y-m-d'));
    }

    public function test_a_given_date_is_kept_and_can_be_changed_while_released(): void
    {
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);
        $this->assertSame('2026-03-01', $this->milestone->fresh()->release_date->format('Y-m-d'));

        $this->milestone->update(['release_date' => '2026-03-05']);
        $this->assertSame('2026-03-05', $this->milestone->fresh()->release_date->format('Y-m-d'));
    }

    public function test_back_to_planned_clears_the_date(): void
    {
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);

        $this->milestone->update(['release_status' => ReleaseStatus::PLANNED]);

        $this->assertNull($this->milestone->fresh()->release_date);
    }

    public function test_a_date_together_with_planned_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->milestone->update(['release_date' => '2026-03-01']);
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('input.releaseDate', $error->errors());

            throw $error;
        }
    }

    public function test_the_release_of_the_api_carries_the_date(): void
    {
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);

        $this->assertSame(['version' => '1.4', 'status' => 'released', 'date' => '2026-03-01'], $this->milestone->fresh()->release);
    }

    public function test_export_writes_the_date_only_when_there_is_one_and_import_reads_it_back(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->milestone->project;
        $todo = Todo::factory()->for($this->milestone)->create();
        Task::factory()->for($todo)->create(['status' => 'done', 'commit' => 'none']);
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);
        $other = Milestone::factory()->for($project)->create();
        $other->update(['release_version' => '1.5', 'release_status' => ReleaseStatus::PLANNED]);

        $document = app(Export::class)->document($project->refresh());

        $this->assertSame(['version' => '1.4', 'status' => 'released', 'date' => '2026-03-01'], $document['milestones'][0]['release']);
        $this->assertSame(['version' => '1.5', 'status' => 'planned'], $document['milestones'][1]['release'], 'a planned version has no date key');

        $document['milestones'][0]['release']['date'] = '2026-04-02';
        $result = app(Import::class)->run($project, $document, ImportMode::Full, false, false, $admin);

        $this->assertTrue($result->ok(), json_encode($result->errors));
        $this->assertSame('2026-04-02', $this->milestone->fresh()->release_date->format('Y-m-d'));
    }

    public function test_an_import_keeps_a_released_version_without_a_date_as_it_is_and_invents_none(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->milestone->project;
        Todo::factory()->for($this->milestone)->create();
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);
        $this->milestone->todos()->delete();

        $document = app(Export::class)->document($project->refresh());
        unset($document['milestones'][0]['release']['date']);

        $result = app(Import::class)->run($project, $document, ImportMode::Full, false, false, $admin);

        $this->assertTrue($result->ok(), json_encode($result->errors));
        $this->assertNull($this->milestone->fresh()->release_date, 'a file without a date leaves the version without a date: no today');
    }

    public function test_the_validator_hints_at_a_released_version_without_date_and_refuses_a_date_on_a_planned_one(): void
    {
        $project = $this->milestone->project;
        $this->milestone->update(['release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-03-01']);
        $document = app(Export::class)->document($project->refresh());

        $this->assertSame([], Validator::validate($document));

        unset($document['milestones'][0]['release']['date']);
        $issues = Validator::validate($document);
        $this->assertSame([['milestones[0].release.date', 'warning']], array_map(fn (array $issue): array => [$issue['path'], $issue['severity']], $issues));
        $this->assertSame([], Validator::errors($document), 'only a hint, no error');

        $document['milestones'][0]['release'] = ['version' => '1.4', 'status' => 'planned', 'date' => '2026-03-01'];
        $this->assertSame([['milestones[0].release.date', 'error']], array_map(fn (array $issue): array => [$issue['path'], $issue['severity']], Validator::validate($document)));
    }
}
