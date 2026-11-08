<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Notifications\TaskAssigned;
use App\Services\ProjectPlanAssistant;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;

/**
 * AI assistant page: Claude drafts a plan (nothing saved), "Jetzt
 * eintragen" imports it all or nothing. Admins only.
 */
class ProjectPlanAssistantTest extends GraphQLTestCase
{
    private const DRAFT = 'mutation ($messages: [PlanChatMessageInput!]!, $currentPlan: ProjectPlanInput) {
        draftProjectPlan(messages: $messages, currentPlan: $currentPlan) {
            reply
            plan { name locale members { email role } milestones { title dueDate todos { title tasks { title dueDate assigneeEmail } } } }
        }
    }';

    private const IMPORT = 'mutation ($plan: ProjectPlanInput!, $notify: Boolean) {
        importProjectPlan(plan: $plan, notifyAssignees: $notify) { id name members { role user { email } } }
    }';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key']);
    }

    public function test_a_draft_is_cleaned_up_and_saves_nothing(): void
    {
        $owner = $this->users['owner']->email;
        $sent = null;

        $this->partialMock(ProjectPlanAssistant::class, function (MockInterface $mock) use ($owner, &$sent) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('ask')->once()
                ->andReturnUsing(function ($client, string $system, array $messages) use ($owner, &$sent) {
                    $sent = $messages;

                    return ['reply' => 'Plan erstellt.', 'plan' => [
                        'name' => 'Podcast',
                        'description' => 'Ein Podcast.',
                        'locale' => 'DE',
                        'members' => [['email' => 'niemand@example.ch', 'role' => 'OWNER']],
                        'milestones' => [[
                            'title' => 'Konzept steht',
                            'description' => null,
                            'dueDate' => '2027-02-30',
                            'todos' => [[
                                'title' => 'Format',
                                'description' => null,
                                'dueDate' => null,
                                'tasks' => [
                                    ['title' => 'Folgen skizzieren', 'description' => null, 'dueDate' => '2027-01-15', 'assigneeEmail' => strtoupper($owner)],
                                    ['title' => 'Gäste suchen', 'description' => null, 'dueDate' => null, 'assigneeEmail' => 'niemand@example.ch'],
                                ],
                            ]],
                        ]],
                    ]];
                });
        });

        $this->as('admin')->gql(self::DRAFT, [
            'messages' => [
                ['role' => 'USER', 'content' => 'Ein Podcast'],
                ['role' => 'ASSISTANT', 'content' => 'Wer macht mit?'],
                ['role' => 'USER', 'content' => 'Ben macht alles'],
            ],
            'currentPlan' => $this->plan(),
        ])
            ->assertJsonPath('data.draftProjectPlan.reply', 'Plan erstellt.')
            ->assertJsonPath('data.draftProjectPlan.plan.locale', 'DE')
            // Unknown people are dropped, assignees with an account become members.
            ->assertJsonPath('data.draftProjectPlan.plan.members', [['email' => $owner, 'role' => 'MEMBER']])
            ->assertJsonPath('data.draftProjectPlan.plan.milestones.0.dueDate', null)
            ->assertJsonPath('data.draftProjectPlan.plan.milestones.0.todos.0.tasks.0.assigneeEmail', $owner)
            ->assertJsonPath('data.draftProjectPlan.plan.milestones.0.todos.0.tasks.0.dueDate', '2027-01-15')
            ->assertJsonPath('data.draftProjectPlan.plan.milestones.0.todos.0.tasks.1.assigneeEmail', null);

        $this->assertSame(['user', 'assistant', 'user'], array_column($sent, 'role'));
        $this->assertStringStartsWith('<current_draft>', $sent[2]['content']);
        $this->assertStringContainsString('"role": "OWNER"', $sent[2]['content']);
        $this->assertStringEndsWith('Ben macht alles', $sent[2]['content']);
        $this->assertSame(1, Project::count());
    }

    public function test_only_admins_use_the_assistant(): void
    {
        $this->assertUnauthorized($this->as('owner')->gql(self::DRAFT, ['messages' => [['role' => 'USER', 'content' => 'Ein Podcast']]]));
        $this->assertUnauthorized($this->as('owner')->gql(self::IMPORT, ['plan' => $this->plan()]));
    }

    public function test_without_an_api_key_the_assistant_says_so(): void
    {
        config(['services.anthropic.key' => null]);

        $this->as('admin')->gql(self::DRAFT, ['messages' => [['role' => 'USER', 'content' => 'Ein Podcast']]])
            ->assertGraphQLErrorMessage(__('projects.plan.not_configured'));
    }

    public function test_import_creates_project_members_and_plan_without_mails(): void
    {
        Notification::fake();

        $id = $this->as('admin')->gql(self::IMPORT, ['plan' => $this->plan()])
            ->assertJsonPath('data.importProjectPlan.name', 'Podcast')
            ->json('data.importProjectPlan.id');

        $project = Project::findOrFail($id);
        $this->assertSame(ProjectRole::OWNER, $project->roleOf($this->users['owner']));
        $this->assertSame(ProjectRole::MEMBER, $project->roleOf($this->users['member']));
        $milestone = Milestone::where('project_id', $id)->sole();
        $this->assertSame('2027-01-31', $milestone->due_date->toDateString());
        $this->assertSame('bulletList', $milestone->description['content'][1]['type']);
        $todo = Todo::where('milestone_id', $milestone->id)->sole();
        $this->assertSame(
            [$this->users['member']->id, null],
            Task::where('todo_id', $todo->id)->orderBy('id')->pluck('assigned_user_id')->all(),
        );
        Notification::assertNothingSent();
    }

    public function test_import_sends_assignment_mails_when_asked_for(): void
    {
        Notification::fake();

        $this->as('admin')->gql(self::IMPORT, ['plan' => $this->plan(), 'notify' => true])->assertJsonPath('data.importProjectPlan.name', 'Podcast');

        Notification::assertSentTo($this->users['member'], TaskAssigned::class);
    }

    public function test_an_assignee_outside_the_members_imports_nothing(): void
    {
        $plan = $this->plan();
        $plan['milestones'][0]['todos'][0]['tasks'][0]['assigneeEmail'] = $this->users['stranger']->email;

        $this->as('admin')->gql(self::IMPORT, ['plan' => $plan])
            ->assertGraphQLValidationError('plan', __('projects.plan.not_a_member', [
                'subject' => 'Hörerprofile schreiben',
                'detail' => $this->users['stranger']->email,
            ]));

        $this->assertSame(1, Project::count());
        $this->assertSame(1, Milestone::count());
    }

    public function test_an_unknown_member_imports_nothing(): void
    {
        $plan = $this->plan();
        $plan['members'][] = ['email' => 'niemand@example.ch', 'role' => 'MEMBER'];

        $this->as('admin')->gql(self::IMPORT, ['plan' => $plan])
            ->assertGraphQLValidationError('plan', __('projects.plan.unknown_person', ['subject' => 'niemand@example.ch']));

        $this->assertSame(1, Project::count());
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(): array
    {
        return [
            'name' => 'Podcast',
            'description' => 'Ein Podcast über Menschen mit Migrationsgeschichte.',
            'locale' => 'DE',
            'members' => [
                ['email' => $this->users['owner']->email, 'role' => 'OWNER'],
                ['email' => $this->users['member']->email, 'role' => 'MEMBER'],
            ],
            'milestones' => [[
                'title' => 'Konzept steht',
                'description' => "Format und Zielgruppe sind klar.\n\n- Format\n- Zielgruppe",
                'dueDate' => '2027-01-31',
                'todos' => [[
                    'title' => 'Zielgruppe festlegen',
                    'description' => null,
                    'dueDate' => null,
                    'tasks' => [
                        ['title' => 'Hörerprofile schreiben', 'description' => null, 'dueDate' => '2027-01-15', 'assigneeEmail' => $this->users['member']->email],
                        ['title' => 'Namen sammeln', 'description' => null, 'dueDate' => null, 'assigneeEmail' => null],
                    ],
                ]],
            ]],
        ];
    }
}
