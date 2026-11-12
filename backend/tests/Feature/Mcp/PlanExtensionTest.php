<?php

namespace Tests\Feature\Mcp;

use App\Enums\ProjectRole;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\AddToPlanTool;
use App\Mcp\Tools\CreateProjectPlanTool;
use App\Mcp\Tools\MoveMilestoneTool;
use App\Mcp\Tools\UpdatePlanItemTool;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan tools that extend an existing plan: ids in the answers, the order
 * of milestones, adding to a milestone or a todo without duplicates, and
 * changing single fields. Same rights as the app, all or nothing.
 */
class PlanExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $stranger;

    private Project $project;

    private Milestone $first;

    private Milestone $second;

    private Todo $todo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'oliver@example.ch']);
        $this->member = User::factory()->create(['email' => 'lindita@example.ch']);
        $this->stranger = User::factory()->create(['email' => 'eva@example.ch']);
        $this->project = Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->create(['name' => 'Podcast']);
        $this->first = Milestone::factory()->for($this->project)->create(['title' => 'Version 1.0', 'position' => 0]);
        $this->second = Milestone::factory()->for($this->project)->create(['title' => 'Version 2.0', 'position' => 1]);
        $this->todo = Todo::factory()->for($this->first)->create(['title' => 'Meldungen']);
        Task::factory()->for($this->todo)->create(['title' => 'Hörerprofile schreiben']);
    }

    public function test_the_plan_answer_lists_the_ids_of_everything_created(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'milestones' => [['title' => 'Version 3.0', 'todos' => [['title' => 'Trailer', 'tasks' => [['title' => 'Schnitt', 'status' => 'done']]]]]],
        ])
            ->assertOk()
            ->assertSee('"items"')
            ->assertSee('"title":"Version 3.0"')
            ->assertSee('"title":"Trailer"')
            ->assertSee('"title":"Schnitt"');

        $this->assertSame(['Version 1.0', 'Version 2.0', 'Version 3.0'], $this->orderOf());
    }

    public function test_a_milestone_can_be_placed_at_a_position_and_the_others_move_along(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'milestones' => [['title' => 'Version 0.9', 'position' => 0]],
        ])->assertOk();

        $this->assertSame(['Version 0.9', 'Version 1.0', 'Version 2.0'], $this->orderOf());
    }

    public function test_move_milestone_puts_it_at_the_given_place_and_renumbers(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(MoveMilestoneTool::class, [
            'milestone_id' => (string) $this->second->id,
            'position' => 0,
        ])
            ->assertOk()
            ->assertSee('"position":0');

        $this->assertSame(['Version 2.0', 'Version 1.0'], $this->orderOf());
        $this->assertSame([1, 0], [$this->first->fresh()->position, $this->second->fresh()->position]);
    }

    public function test_adding_todos_to_a_milestone_keeps_the_existing_ones(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'milestone_id' => (string) $this->first->id,
            'todos' => [['title' => 'Trailer', 'tasks' => [['title' => 'Schnitt']]]],
        ])
            ->assertOk()
            ->assertSee('"title":"Trailer"');

        $this->assertSame(['Meldungen', 'Trailer'], $this->first->todos()->orderBy('id')->pluck('title')->all());
    }

    public function test_adding_tasks_to_a_todo_appends_them(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'todo_id' => (string) $this->todo->id,
            'tasks' => [['title' => 'Antworten formulieren', 'status' => 'done']],
        ])->assertOk();

        $this->assertSame(['Hörerprofile schreiben', 'Antworten formulieren'], $this->todo->tasks()->orderBy('id')->pluck('title')->all());
        $this->assertSame('done', $this->todo->tasks()->where('title', 'Antworten formulieren')->value('status')->value);
    }

    public function test_a_task_title_that_already_exists_in_the_todo_stops_everything(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'todo_id' => (string) $this->todo->id,
            'tasks' => [['title' => 'Neue Aufgabe'], ['title' => 'hörerprofile schreiben']],
        ])->assertHasErrors(['Task "hörerprofile schreiben" already exists in todo "Meldungen". Nothing was created.']);

        $this->assertSame(1, $this->todo->tasks()->count());
    }

    public function test_a_todo_title_that_already_exists_in_the_milestone_stops_everything(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'milestone_id' => (string) $this->first->id,
            'todos' => [['title' => 'Meldungen']],
        ])->assertHasErrors(['Todo "Meldungen" already exists in milestone "Version 1.0". Nothing was created.']);

        $this->assertSame(1, $this->first->todos()->count());
    }

    public function test_exactly_one_target_is_required(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'milestone_id' => (string) $this->first->id,
            'todo_id' => (string) $this->todo->id,
            'todos' => [['title' => 'Trailer']],
        ])->assertHasErrors(['Give either milestone_id or todo_id, not both.']);
    }

    public function test_a_member_may_not_add_tasks_and_nothing_changes(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(AddToPlanTool::class, [
            'project_id' => (string) $this->project->id,
            'todo_id' => (string) $this->todo->id,
            'tasks' => [['title' => 'Antworten formulieren']],
        ])->assertHasErrors(['Task "Antworten formulieren": You may not add to this plan. Nothing was changed.']);

        $this->assertSame(1, $this->todo->tasks()->count());
    }

    public function test_a_stranger_gets_an_error_when_moving_a_milestone(): void
    {
        MolloPlanerServer::actingAs($this->stranger)->tool(MoveMilestoneTool::class, [
            'milestone_id' => (string) $this->second->id,
            'position' => 0,
        ])->assertHasErrors(['You may not move this milestone. Nothing was changed.']);

        $this->assertSame(['Version 1.0', 'Version 2.0'], $this->orderOf());
    }

    public function test_update_plan_item_changes_only_the_given_fields(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(UpdatePlanItemTool::class, [
            'type' => 'todo',
            'id' => (string) $this->todo->id,
            'due_date' => '2027-03-31',
        ])->assertOk();

        $this->todo->refresh();
        $this->assertSame('Meldungen', $this->todo->title);
        $this->assertSame('2027-03-31', $this->todo->due_date->format('Y-m-d'));
    }

    public function test_update_plan_item_renames_a_milestone(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(UpdatePlanItemTool::class, [
            'type' => 'milestone',
            'id' => (string) $this->first->id,
            'title' => 'Version 1.1',
        ])->assertOk();

        $this->assertSame('Version 1.1', $this->first->fresh()->title);
    }

    public function test_update_plan_item_without_fields_changes_nothing(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(UpdatePlanItemTool::class, [
            'type' => 'todo',
            'id' => (string) $this->todo->id,
        ])->assertHasErrors(['Give at least one field to change: title, description or due_date. Nothing was changed.']);
    }

    public function test_the_tools_are_listed(): void
    {
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertSee('add_to_plan')
            ->assertSee('update_plan_item')
            ->assertSee('move_milestone');
    }

    /**
     * @return list<string>
     */
    private function orderOf(): array
    {
        return $this->project->milestones()->pluck('title')->all();
    }
}
