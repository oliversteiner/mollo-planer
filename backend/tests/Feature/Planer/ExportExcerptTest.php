<?php

namespace Tests\Feature\Planer;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\Export;
use App\Planer\ExportFilter;
use App\Planer\Validator;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * export_plan with a filter gives an excerpt (task 30 A): a valid planer-v2 document marked excerpt: true.
 */
class ExportExcerptTest extends GraphQLTestCase
{
    private Milestone $released;

    private Milestone $planned;

    protected function setUp(): void
    {
        parent::setUp();

        // $this->milestone (1.0, planned) has one task of the base setup; make versions and statuses explicit.
        $this->milestone->forceFill(['release_version' => '1.0', 'release_status' => ReleaseStatus::PLANNED])->save();
        $this->task->forceFill(['status' => TaskStatus::OPEN])->save();
        $this->planned = $this->milestone;

        $this->released = Milestone::factory()->for($this->project)->create(['position' => 1]);
        $todo = Todo::factory()->for($this->released)->create();
        Task::factory()->for($todo)->create(['board_position' => 1, 'status' => TaskStatus::DONE, 'commit' => 'none']);
        $this->released->forceFill(['release_version' => '0.9', 'release_status' => ReleaseStatus::RELEASED, 'release_date' => '2026-01-01'])->save();

        $third = Milestone::factory()->for($this->project)->create(['position' => 2]);
        $todo = Todo::factory()->for($third)->create();
        Task::factory()->for($todo)->create(['board_position' => 2, 'status' => TaskStatus::REVIEW]);
        Task::factory()->for($todo)->create(['board_position' => 3, 'status' => TaskStatus::PLANNED]);
    }

    /** @return list<string> */
    private function titles(?ExportFilter $filter): array
    {
        $document = app(Export::class)->document($this->project->fresh(), $filter);
        $this->assertSame([], Validator::errors($document), 'an excerpt is a valid file');

        return array_column($document['milestones'], 'id');
    }

    public function test_without_a_filter_it_is_the_whole_plan_without_the_marker(): void
    {
        $document = app(Export::class)->document($this->project->fresh(), new ExportFilter);

        $this->assertArrayNotHasKey('excerpt', $document);
        $this->assertCount(3, $document['milestones']);
    }

    public function test_each_filter_and_the_combination(): void
    {
        $this->assertSame(["m-{$this->released->id}"], $this->titles(new ExportFilter(version: '0.9')));
        $this->assertSame(["m-{$this->planned->id}"], $this->titles(new ExportFilter(milestoneIds: [$this->planned->id])));
        $this->assertCount(2, $this->titles(new ExportFilter(includeReleased: false)));

        $open = app(Export::class)->document($this->project->fresh(), new ExportFilter(taskStatus: ['review']));
        $this->assertCount(1, $open['milestones']);
        $this->assertCount(1, $open['milestones'][0]['todos'][0]['tasks'], 'only the review task stays');

        $this->assertSame([], $this->titles(new ExportFilter(version: '0.9', taskStatus: ['open'])), 'the version and a status nobody has');
        $this->assertCount(1, $this->titles(new ExportFilter(includeReleased: false, taskStatus: ['open'])));
    }

    public function test_an_excerpt_is_marked_and_keeps_the_revision(): void
    {
        $document = app(Export::class)->document($this->project->fresh(), new ExportFilter(version: '1.0'));

        $this->assertTrue($document['excerpt']);
        $this->assertSame((int) $this->project->fresh()->planer_revision, $document['revision']);
    }

    public function test_the_mcp_tool_filters(): void
    {
        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'version' => '0.9'])
            ->assertOk()
            ->assertSee('"excerpt":true')
            ->assertSee("m-{$this->released->id}")
            ->assertDontSee("m-{$this->planned->id}\"");

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'task_status' => ['review'], 'include_released' => false])
            ->assertOk()
            ->assertSee('"excerpt":true');
    }

    public function test_import_full_refuses_an_excerpt_and_partial_takes_it(): void
    {
        $document = app(Export::class)->document($this->project->fresh(), new ExportFilter(version: '1.0'));

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('import_plan'), ['project_id' => (string) $this->project->id, 'plan' => $document, 'dry_run' => true])
            ->assertHasErrors()
            ->assertSee('excerpt');
        $this->assertCount(3, Milestone::query()->where('project_id', $this->project->id)->get());

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('import_plan'), ['project_id' => (string) $this->project->id, 'plan' => $document, 'mode' => 'partial', 'dry_run' => true])
            ->assertOk();
    }

    public function test_partial_adds_a_new_milestone_with_todos_and_tasks_from_an_excerpt(): void
    {
        $document = app(Export::class)->document($this->project->fresh(), new ExportFilter(version: '1.0'));
        $document['milestones'][] = [
            'id' => 'm-new-1',
            'title' => 'Version 1.1',
            'release' => ['version' => '1.1', 'status' => 'planned'],
            'todos' => [[
                'id' => 't-new-1',
                'title' => 'Neues ToDo',
                'tasks' => [['id' => 'k-new-1', 'title' => 'Neue Aufgabe', 'status' => 'open']],
            ]],
        ];

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('import_plan'), ['project_id' => (string) $this->project->id, 'plan' => $document, 'mode' => 'partial'])
            ->assertOk()
            ->assertSee('"m-new-1"');

        $this->assertNotNull(Milestone::query()->where('title', 'Version 1.1')->first());
        $this->assertNotNull(Task::query()->where('title', 'Neue Aufgabe')->first());
        $this->assertCount(4, Milestone::query()->where('project_id', $this->project->id)->get(), 'the other milestones stay');
    }

    public function test_the_filter_needs_the_export_right(): void
    {
        MolloPlanerServer::actingAs($this->users['stranger'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'version' => '1.0'])
            ->assertHasErrors();
    }
}
