<?php

namespace Tests\Feature\Mcp;

use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\Mcp\Servers\ProjektverwaltungServer;
use App\Mcp\Tools\CreateProjectPlanTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Support\McpTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The MCP server acts through the GraphQL schema as the token's owner:
 * same permissions and rules as the app, plans are created all or nothing.
 */
class ProjektverwaltungServerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $member;

    private User $stranger;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'anna@example.ch']);
        $this->owner = User::factory()->create(['email' => 'oliver@example.ch']);
        $this->member = User::factory()->create(['email' => 'lindita@example.ch']);
        $this->stranger = User::factory()->create(['email' => 'eva@example.ch']);
        $this->project = Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->create(['name' => 'Podcast']);
    }

    public function test_list_projects_shows_only_what_the_token_owner_may_see(): void
    {
        Project::factory()->create(['name' => 'Fremd']);

        ProjektverwaltungServer::actingAs($this->member)->tool(ListProjectsTool::class)
            ->assertOk()
            ->assertSee('Podcast')
            ->assertDontSee('Fremd');
    }

    public function test_get_project_refuses_a_foreign_project(): void
    {
        ProjektverwaltungServer::actingAs($this->stranger)->tool(GetProjectTool::class, ['project_id' => (string) $this->project->id])
            ->assertHasErrors(['This project does not exist, or you have no access to it.']);
    }

    public function test_only_admins_create_projects(): void
    {
        ProjektverwaltungServer::actingAs($this->owner)->tool(CreateProjectTool::class, ['name' => 'Neu'])
            ->assertHasErrors(['This action is unauthorized.']);

        $this->assertSame(1, Project::count());
    }

    public function test_admin_creates_a_project_with_members_by_email(): void
    {
        ProjektverwaltungServer::actingAs($this->admin)->tool(CreateProjectTool::class, [
            'name' => 'Podcast Migration',
            'members' => [
                ['email' => 'Oliver@example.ch', 'role' => 'OWNER'],
                ['email' => 'lindita@example.ch', 'role' => 'MEMBER'],
            ],
        ])->assertOk()->assertSee('Podcast Migration');

        $project = Project::where('name', 'Podcast Migration')->firstOrFail();
        $this->assertSame(ProjectRole::OWNER, $project->roleOf($this->owner));
        $this->assertSame(ProjectRole::MEMBER, $project->roleOf($this->member));
    }

    public function test_an_unknown_member_creates_no_project(): void
    {
        ProjektverwaltungServer::actingAs($this->admin)->tool(CreateProjectTool::class, [
            'name' => 'Podcast Migration',
            'members' => [['email' => 'niemand@example.ch', 'role' => 'MEMBER']],
        ])->assertHasErrors(['No person with the e-mail niemand@example.ch has an account. An admin must invite them in the app first.']);

        $this->assertFalse(Project::where('name', 'Podcast Migration')->exists());
    }

    public function test_owner_creates_a_whole_plan_with_assignments_and_no_mails_by_default(): void
    {
        Notification::fake();

        ProjektverwaltungServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $this->plan())
            ->assertOk()
            ->assertSee('"tasks":3');

        $milestone = Milestone::where('title', 'Konzept steht')->firstOrFail();
        $todo = Todo::where('title', 'Zielgruppe festlegen')->firstOrFail();
        $this->assertSame($this->project->id, $milestone->project_id);
        $this->assertSame('2027-01-31', $milestone->due_date->toDateString());
        $this->assertSame('bulletList', $milestone->description['content'][1]['type']);
        $this->assertSame(ItemStatus::OPEN, $todo->fresh()->status);
        $this->assertSame([$this->member->id, $this->owner->id, null], Task::orderBy('id')->pluck('assigned_user_id')->all());
        Notification::assertNothingSent();
    }

    public function test_assignment_mails_are_sent_when_asked_for(): void
    {
        Notification::fake();

        ProjektverwaltungServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, [...$this->plan(), 'notify_assignees' => true])
            ->assertOk();

        Notification::assertSentTo($this->member, TaskAssigned::class);
    }

    public function test_a_member_may_not_create_tasks_and_then_nothing_is_created(): void
    {
        ProjektverwaltungServer::actingAs($this->member)->tool(CreateProjectPlanTool::class, $this->plan())
            ->assertHasErrors(['Task "Hörerprofile schreiben": This action is unauthorized.']);

        $this->assertSame([0, 0, 0], [Milestone::count(), Todo::count(), Task::count()]);
    }

    public function test_assigning_someone_outside_the_project_creates_nothing(): void
    {
        $plan = $this->plan();
        $plan['milestones'][0]['todos'][0]['tasks'][0]['assignee_email'] = 'eva@example.ch';

        ProjektverwaltungServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $plan)
            ->assertHasErrors(['Task "Hörerprofile schreiben": eva@example.ch is not a member of this project. Add them with set_project_member first, or leave the task unassigned.']);

        $this->assertSame([0, 0, 0], [Milestone::count(), Todo::count(), Task::count()]);
    }

    public function test_invalid_dates_are_explained(): void
    {
        $plan = $this->plan();
        $plan['milestones'][0]['due_date'] = '31.01.2027';

        ProjektverwaltungServer::actingAs($this->owner)->tool(CreateProjectPlanTool::class, $plan)
            ->assertHasErrors(['Dates are YYYY-MM-DD, for example 2027-03-31.']);
    }

    public function test_http_endpoint_needs_a_token_made_for_claude(): void
    {
        $list = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $headers = ['Accept' => 'application/json, text/event-stream'];

        $this->postJson('/mcp', $list, $headers)->assertUnauthorized();

        $other = $this->owner->createToken('other')->plainTextToken;
        $this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$other}"])->assertForbidden();

        // Each real request starts fresh; the test app keeps the resolved user.
        $this->app['auth']->forgetGuards();
        $token = McpTokens::create($this->owner, 'Claude')->plainTextToken;
        $this->postJson('/mcp', $list, [...$headers, 'Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertSee('create_project_plan');
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(): array
    {
        return [
            'project_id' => (string) $this->project->id,
            'milestones' => [[
                'title' => 'Konzept steht',
                'description' => "Format, Zielgruppe und Ablauf sind festgelegt.\n\n- Format\n- Zielgruppe",
                'due_date' => '2027-01-31',
                'todos' => [[
                    'title' => 'Zielgruppe festlegen',
                    'tasks' => [
                        ['title' => 'Hörerprofile schreiben', 'assignee_email' => 'lindita@example.ch', 'due_date' => '2027-01-15'],
                        ['title' => 'Technik-Setup vergleichen', 'assignee_email' => 'OLIVER@example.ch'],
                        ['title' => 'Namen sammeln'],
                    ],
                ]],
            ]],
        ];
    }
}
