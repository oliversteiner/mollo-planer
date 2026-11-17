<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectRole;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Tools\AddToPlanTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\SetTaskStatusTool;
use App\Mcp\Tools\UpdatePlanItemTool;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;

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

    public function test_writing_tools_refuse_an_archived_project_and_say_why(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'done'])
            ->assertHasErrors([GraphQLFailed::ARCHIVED]);
        MolloPlanerServer::actingAs($this->owner)->tool(UpdatePlanItemTool::class, ['type' => 'todo', 'id' => (string) $this->todo->id, 'title' => 'Neu'])
            ->assertHasErrors([GraphQLFailed::ARCHIVED]);
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'todo_id' => (string) $this->todo->id,
            'tasks' => [['title' => 'Noch ein Task']],
        ])->assertHasErrors([GraphQLFailed::ARCHIVED]);

        $this->assertSame('Vorbereiten', $this->todo->fresh()->title);
        $this->assertSame(1, Task::count());
    }

    public function test_reading_an_archived_project_works(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(GetProjectTool::class, ['project_id' => (string) $this->project->id])
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
