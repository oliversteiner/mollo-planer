<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectRole;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Tools\ExportPlanTool;
use App\Mcp\Tools\ImportPlanTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\Export;

/**
 * The MCP tools and an archived project (docs/auftrag-m2.md §1.7): reading
 * works, writing is refused with one clear sentence, list_projects leaves
 * the archive out unless asked.
 */
class ArchivedProjectToolsTest extends McpTestCase
{
    private Milestone $milestone;

    private Todo $todo;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->milestone = Milestone::factory()->for($this->project)->create(['title' => 'Start']);
        $this->todo = Todo::factory()->for($this->milestone)->create(['title' => 'Vorbereiten']);
        $this->task = Task::factory()->for($this->todo)->create(['title' => 'Hörerprofile schreiben']);
        $this->project->forceFill(['archived_at' => now()])->save();
    }

    public function test_import_refuses_an_archived_project_and_says_why(): void
    {
        $document = app(Export::class)->document($this->project->fresh());
        $document['milestones'][0]['todos'][0]['title'] = 'Neu';

        MolloPlanerServer::actingAs($this->owner)->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document])
            ->assertHasErrors([GraphQLFailed::ARCHIVED]);

        $this->assertSame('Vorbereiten', $this->todo->fresh()->title);
    }

    public function test_reading_an_archived_project_works(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(ExportPlanTool::class, ['project_id' => (string) $this->project->id])
            ->assertOk()
            ->assertSee('Hörerprofile schreiben');
    }

    public function test_list_projects_shows_the_archive_only_when_asked(): void
    {
        Project::factory()->withMember($this->member, ProjectRole::MEMBER)->create(['name' => 'Aktiv']);

        MolloPlanerServer::actingAs($this->member)->tool(ListProjectsTool::class)
            ->assertOk()
            ->assertSee('Aktiv')
            ->assertDontSee('Podcast');
        MolloPlanerServer::actingAs($this->member)->tool(ListProjectsTool::class, ['archived' => true])
            ->assertOk()
            ->assertSee('Podcast')
            ->assertDontSee('Aktiv');
    }
}
