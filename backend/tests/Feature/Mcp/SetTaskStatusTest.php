<?php

namespace Tests\Feature\Mcp;

use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\CreateProjectPlanTool;
use App\Mcp\Tools\SetTaskStatusTool;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * set_task_status and the task statuses of create_project_plan: same rights
 * and automation as the Kanban board, no mails, nothing else changed.
 */
class SetTaskStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $member;

    private User $stranger;

    private Project $project;

    private Milestone $milestone;

    private Todo $todo;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'oliver@example.ch']);
        $this->manager = User::factory()->create(['email' => 'chiara@example.ch']);
        $this->member = User::factory()->create(['email' => 'lindita@example.ch']);
        $this->stranger = User::factory()->create(['email' => 'eva@example.ch']);
        $this->project = Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->manager, ProjectRole::MANAGER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->create(['name' => 'Podcast']);
        $this->milestone = Milestone::factory()->for($this->project)->create(['title' => 'Bestehender Meilenstein']);
        $this->todo = Todo::factory()->for($this->milestone)->create(['title' => 'Bestehendes ToDo']);
        $this->task = Task::factory()->for($this->todo)->create(['title' => 'Hörerprofile schreiben']);
    }

    public function test_a_member_marks_the_last_open_task_done_and_todo_and_milestone_follow(): void
    {
        MolloPlanerServer::actingAs($this->member)->tool(SetTaskStatusTool::class, [
            'task_id' => (string) $this->task->id,
            'status' => 'done',
        ])
            ->assertOk()
            ->assertSee('"task_id":"'.$this->task->id.'"')
            ->assertSee('"status":"done"')
            ->assertSee('"todo":{"id":"'.$this->todo->id.'","status":"done"}')
            ->assertSee('"milestone":{"id":"'.$this->milestone->id.'","status":"done"}');

        $this->assertSame(TaskStatus::DONE, $this->task->fresh()->status);
        $this->assertSame(ItemStatus::DONE, $this->todo->fresh()->status);
        $this->assertSame(ItemStatus::DONE, $this->milestone->fresh()->status);
    }

    public function test_owner_and_manager_may_set_the_status_too(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'review'])
            ->assertOk()
            ->assertSee('"status":"review"');
        $this->assertSame(TaskStatus::REVIEW, $this->task->fresh()->status);

        MolloPlanerServer::actingAs($this->manager)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'in_progress'])
            ->assertOk();
        $this->assertSame(TaskStatus::IN_PROGRESS, $this->task->fresh()->status);
    }

    public function test_a_stranger_gets_an_error_and_nothing_changes(): void
    {
        MolloPlanerServer::actingAs($this->stranger)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'done'])
            ->assertHasErrors(['You may not change this task. Only members of its project can change task statuses; nothing was changed.']);

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
        $this->assertSame(ItemStatus::OPEN, $this->milestone->fresh()->status);
    }

    public function test_an_unknown_task_gets_a_clear_error(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(SetTaskStatusTool::class, ['task_id' => '999999', 'status' => 'done'])
            ->assertHasErrors(['This task does not exist, or you have no access to it. Nothing was changed.']);
    }

    public function test_an_invalid_status_lists_the_valid_values_and_changes_nothing(): void
    {
        MolloPlanerServer::actingAs($this->owner)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'fertig'])
            ->assertHasErrors(['Unknown status. Use one of: open, in_progress, review, done.']);

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
    }

    public function test_reopening_a_task_reopens_todo_and_milestone(): void
    {
        $this->task->forceFill(['status' => TaskStatus::DONE])->save();
        $this->todo->fresh()->forceFill(['status' => ItemStatus::DONE])->saveQuietly();
        $this->milestone->fresh()->forceFill(['status' => ItemStatus::DONE])->saveQuietly();

        MolloPlanerServer::actingAs($this->member)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'open'])
            ->assertOk()
            ->assertSee('"todo":{"id":"'.$this->todo->id.'","status":"open"}')
            ->assertSee('"milestone":{"id":"'.$this->milestone->id.'","status":"open"}');

        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);
        $this->assertSame(ItemStatus::OPEN, $this->milestone->fresh()->status);
    }

    public function test_the_task_lands_at_the_end_of_its_column(): void
    {
        $done = Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE, 'position' => 0]);

        MolloPlanerServer::actingAs($this->member)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'done'])->assertOk();

        $this->assertSame(0, $done->fresh()->position);
        $this->assertSame(1, $this->task->fresh()->position);
    }

    public function test_the_tool_sends_no_mails_and_does_not_rename_or_reassign(): void
    {
        $assignee = $this->member;
        $this->task->forceFill(['assigned_user_id' => $assignee->id])->save();

        MolloPlanerServer::actingAs($this->owner)->tool(SetTaskStatusTool::class, ['task_id' => (string) $this->task->id, 'status' => 'done'])->assertOk();

        $this->assertSame('Hörerprofile schreiben', $this->task->fresh()->title);
        $this->assertSame($assignee->id, $this->task->fresh()->assigned_user_id);
    }

    public function test_a_plan_task_with_status_done_marks_task_todo_and_milestone_done(): void
    {
        $plan = $this->plan('done');
        $plan['project_id'] = (string) $this->project->id;

        MolloPlanerServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $plan)->assertOk();

        $milestone = Milestone::where('title', 'Konzept steht')->firstOrFail();
        $todo = Todo::where('title', 'Zielgruppe festlegen')->firstOrFail();
        $this->assertSame(ItemStatus::DONE, $milestone->fresh()->status);
        $this->assertSame(ItemStatus::DONE, $todo->fresh()->status);
        $this->assertSame([TaskStatus::DONE, TaskStatus::DONE], Task::where('todo_id', $todo->id)->orderBy('id')->get()->pluck('status')->all());
    }

    public function test_a_plan_without_status_keeps_everything_open(): void
    {
        $plan = $this->plan(null);
        $plan['project_id'] = (string) $this->project->id;

        MolloPlanerServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $plan)->assertOk();

        $this->assertSame(0, Task::where('status', TaskStatus::DONE->value)->count());
        $this->assertSame(ItemStatus::OPEN, Todo::where('title', 'Zielgruppe festlegen')->firstOrFail()->status);
    }

    public function test_a_plan_with_an_unknown_task_status_creates_nothing(): void
    {
        $plan = $this->plan('review');
        $plan['project_id'] = (string) $this->project->id;

        $before = [Milestone::count(), Todo::count(), Task::count()];

        MolloPlanerServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $plan)
            ->assertHasErrors(['The status of a task is open or done.']);

        $this->assertSame($before, [Milestone::count(), Todo::count(), Task::count()]);
    }

    public function test_the_tool_is_listed_with_its_statuses(): void
    {
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertSee('set_task_status')
            ->assertSee('in_progress');
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(?string $status): array
    {
        $task = ['title' => 'Technik-Setup vergleichen'];

        if ($status !== null) {
            $task['status'] = $status;
        }

        return [
            'project_id' => '',
            'milestones' => [[
                'title' => 'Konzept steht',
                'todos' => [[
                    'title' => 'Zielgruppe festlegen',
                    'tasks' => [$task, ['title' => 'Namen sammeln', ...($status === null ? [] : ['status' => $status])]],
                ]],
            ]],
        ];
    }
}
