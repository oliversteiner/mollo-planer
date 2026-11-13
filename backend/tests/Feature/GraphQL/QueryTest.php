<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QueryTest extends GraphQLTestCase
{
    public function test_guest_is_rejected(): void
    {
        $this->gql('{ me { id } }')->assertGraphQLErrorMessage('Unauthenticated.');
    }

    public function test_me_with_enum_and_boolean_fields(): void
    {
        $this->as('admin')->gql('{ me { name role notifyByEmail } }')
            ->assertExactJson(['data' => ['me' => ['name' => 'Anna Admin', 'role' => 'ADMIN', 'notifyByEmail' => true]]]);
    }

    public function test_projects_are_filtered_by_visibility(): void
    {
        Project::factory()->create(['name' => 'Other']);

        $this->as('member')->gql('{ projects { name } }')
            ->assertJsonPath('data.projects', [['name' => 'Mollo Planer']]);

        $this->as('admin')->gql('{ projects { name } }')
            ->assertJsonCount(2, 'data.projects');
    }

    public function test_invisible_project_is_null(): void
    {
        $this->as('stranger')->gql('query ($id: ID!) { project(id: $id) { name } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project', null);
    }

    public function test_project_viewer_fields_members_and_locale(): void
    {
        $response = $this->as('member')->gql('query ($id: ID!) {
            project(id: $id) {
                locale viewerRole viewerAbilities
                members { user { name } role }
            }
        }', ['id' => $this->project->id]);

        $response->assertJsonPath('data.project.locale', 'DE')
            ->assertJsonPath('data.project.viewerRole', 'MEMBER')
            ->assertJsonPath('data.project.viewerAbilities', [
                'MILESTONE_CREATE', 'MILESTONE_UPDATE', 'TODO_CREATE', 'TODO_UPDATE', 'TASK_CREATE', 'TASK_MOVE', 'TASK_UPDATE_OWN', 'TASK_ASSIGN_SELF', 'COMMENT_CREATE',
            ])
            ->assertJsonPath('data.project.members', [
                ['user' => ['name' => 'Ben Owner'], 'role' => 'OWNER'],
                ['user' => ['name' => 'Chiara Manager'], 'role' => 'MANAGER'],
                ['user' => ['name' => 'David Member'], 'role' => 'MEMBER'],
            ]);
    }

    public function test_admin_without_membership_has_no_role_but_all_abilities(): void
    {
        $response = $this->as('admin')->gql('query ($id: ID!) { project(id: $id) { viewerRole viewerAbilities } }', ['id' => $this->project->id]);

        $response->assertJsonPath('data.project.viewerRole', null)
            ->assertJsonCount(count(ProjectAbility::cases()), 'data.project.viewerAbilities');
    }

    public function test_progress_on_all_levels(): void
    {
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);

        $this->as('member')->gql('query ($id: ID!) {
            project(id: $id) {
                progress { total done percent }
                milestones { status progress { percent } todos { status progress { done } } }
            }
        }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.progress', ['total' => 3, 'done' => 2, 'percent' => 67])
            ->assertJsonPath('data.project.milestones.0.status', 'OPEN')
            ->assertJsonPath('data.project.milestones.0.progress.percent', 67)
            ->assertJsonPath('data.project.milestones.0.todos.0.progress.done', 2);
    }

    public function test_progress_in_lists_is_counted_per_object(): void
    {
        $emptyTodo = Todo::factory()->for($this->milestone)->create();
        $doneTodo = Todo::factory()->for(Milestone::factory()->for($this->project))->create();
        Task::factory()->for($doneTodo)->create(['status' => TaskStatus::DONE]);
        $otherProject = Project::factory()->withMember($this->users['member'], ProjectRole::MEMBER)->create(['name' => 'Other']);
        Task::factory()->for(Todo::factory()->for(Milestone::factory()->for($otherProject)))->count(2)->create();

        $response = $this->as('member')->gql('{
            projects { name progress { total done } milestones { progress { total done } todos { id progress { total done percent } } } }
        }');

        $projects = collect($response->json('data.projects'))->keyBy('name');
        $this->assertSame(['total' => 2, 'done' => 1], $projects['Mollo Planer']['progress']);
        $this->assertSame(['total' => 2, 'done' => 0], $projects['Other']['progress']);
        $this->assertSame(
            [['total' => 1, 'done' => 0], ['total' => 1, 'done' => 1]],
            collect($projects['Mollo Planer']['milestones'])->pluck('progress')->all(),
        );

        $todos = collect($projects['Mollo Planer']['milestones'])->pluck('todos')->flatten(1)->keyBy('id');
        $this->assertSame(['total' => 1, 'done' => 0, 'percent' => 0], $todos[$this->todo->id]['progress']);
        $this->assertSame(['total' => 0, 'done' => 0, 'percent' => 0], $todos[$emptyTodo->id]['progress']);
        $this->assertSame(['total' => 1, 'done' => 1, 'percent' => 100], $todos[$doneTodo->id]['progress']);
    }

    /**
     * Lists must not load per row (N+1): the number of queries stays the
     * same with more projects, and lazy loading (off in production,
     * an error otherwise) never happens.
     */
    public function test_list_queries_do_not_grow_with_the_number_of_rows(): void
    {
        $query = '{
            projects { members { user { name } role } viewerRole viewerAbilities progress { percent } milestones { progress { done } todos { progress { done } } } }
            dashboard { upcomingTodos(limit: 50) { progress { done } } myOpenTasks { todo { milestone { project { viewerRole } } } } }
            myTasks { comments { author { name } viewerCanEdit viewerCanDelete } }
        }';

        $this->addProjectWithTaskForMember();
        $this->as('member')->gql($query);
        $queriesBefore = $this->countQueries(fn () => $this->as('member')->gql($query)->assertJsonMissingPath('errors'));

        $this->addProjectWithTaskForMember();
        $this->addProjectWithTaskForMember();
        $queriesAfter = $this->countQueries(fn () => $this->as('member')->gql($query)->assertJsonMissingPath('errors'));

        $this->assertSame($queriesBefore, $queriesAfter);
    }

    public function test_user_list_with_security_flags_does_not_grow_with_the_number_of_users(): void
    {
        $query = '{ users { id twoFactorConfirmed hasPasskey } }';
        $this->as('admin')->gql($query);
        $queriesBefore = $this->countQueries(fn () => $this->as('admin')->gql($query)->assertJsonMissingPath('errors'));

        User::factory()->count(3)->create()->each(fn (User $user) => $user->passkeys()->forceCreate([
            'user_id' => $user->id, 'name' => 'Laptop', 'credential_id' => 'c'.$user->id, 'credential' => [],
        ]));
        $response = null;
        $queriesAfter = $this->countQueries(function () use ($query, &$response) {
            $response = $this->as('admin')->gql($query)->assertJsonMissingPath('errors');
        });

        $this->assertSame($queriesBefore, $queriesAfter);
        $this->assertSame(3, collect($response->json('data.users'))->where('hasPasskey', true)->count());
    }

    private function addProjectWithTaskForMember(): void
    {
        $project = Project::factory()
            ->withMember($this->users['member'], ProjectRole::MEMBER)
            ->withMember($this->users['owner'], ProjectRole::OWNER)
            ->create();
        $task = Task::factory()
            ->for(Todo::factory()->for(Milestone::factory()->for($project)))
            ->create(['assigned_user_id' => $this->users['member']->id]);
        Comment::factory()->for($task)->for($this->users['owner'], 'author')->create();
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    public function test_todo_list_filters_by_project_and_status(): void
    {
        $doneTodo = Todo::factory()->for($this->milestone)->create();
        Task::factory()->for($doneTodo)->create(['status' => TaskStatus::DONE]);
        Todo::factory()->create(); // another project

        $this->as('member')->gql('query ($p: ID!) { todos(projectId: $p, status: DONE) { id status } }', ['p' => $this->project->id])
            ->assertJsonPath('data.todos', [['id' => (string) $doneTodo->id, 'status' => 'DONE']]);

        $this->as('member')->gql('query ($p: ID!) { todos(projectId: $p) { id } }', ['p' => $this->project->id])
            ->assertJsonCount(2, 'data.todos');

        $this->as('stranger')->gql('query ($p: ID!) { todos(projectId: $p) { id } }', ['p' => $this->project->id])
            ->assertJsonPath('data.todos', []);
    }

    public function test_task_detail_with_comments_oldest_first(): void
    {
        $mine = Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create(['body' => $this->doc('first')]);
        Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create(['body' => $this->doc('second')]);

        $response = $this->as('member')->gql('query ($id: ID!) {
            task(id: $id) {
                commentCount
                comments { id author { name } body viewerCanEdit viewerCanDelete }
            }
        }', ['id' => $this->task->id]);

        $response->assertJsonPath('data.task.commentCount', 2)
            ->assertJsonPath('data.task.comments.0.id', (string) $mine->id)
            ->assertJsonPath('data.task.comments.0.body', $this->doc('first'))
            ->assertJsonPath('data.task.comments.0.viewerCanEdit', true)
            ->assertJsonPath('data.task.comments.1.author.name', 'Ben Owner')
            ->assertJsonPath('data.task.comments.1.viewerCanEdit', false)
            ->assertJsonPath('data.task.comments.1.viewerCanDelete', false);
    }

    public function test_kanban_columns_sorted_by_board_position(): void
    {
        $second = Task::factory()->for($this->todo)->create(['board_position' => 2]);
        $first = Task::factory()->for($this->todo)->create(['board_position' => 1]);
        $review = Task::factory()->for($this->todo)->create(['status' => TaskStatus::REVIEW]);

        $response = $this->as('member')->gql('query ($p: ID!) { kanban(projectId: $p) { status tasks { id } } }', ['p' => $this->project->id]);

        $response->assertJsonPath('data.kanban.0', ['status' => 'IDEA', 'tasks' => []])
            ->assertJsonPath('data.kanban.1', ['status' => 'PLANNED', 'tasks' => []])
            ->assertJsonPath('data.kanban.2.status', 'OPEN')
            ->assertJsonPath('data.kanban.2.tasks', [
                ['id' => (string) $this->task->id], ['id' => (string) $first->id], ['id' => (string) $second->id],
            ])
            ->assertJsonPath('data.kanban.3', ['status' => 'IN_PROGRESS', 'tasks' => []])
            ->assertJsonPath('data.kanban.4.tasks', [['id' => (string) $review->id]])
            ->assertJsonPath('data.kanban.5.status', 'DONE');

        $this->as('stranger')->gql('query ($p: ID!) { kanban(projectId: $p) { status } }', ['p' => $this->project->id])
            ->assertJsonPath('data.kanban', []);
    }

    /**
     * Optional filters (@filter): an explicit null, as clients send it for
     * "all", applies no filter. With @eq it would mean "IS NULL" and find nothing.
     */
    public function test_null_filters_apply_no_filter(): void
    {
        $doneTodo = Todo::factory()->for($this->milestone)->create();
        Task::factory()->for($doneTodo)->create(['status' => TaskStatus::DONE, 'assigned_user_id' => $this->users['member']->id]);
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id])->save();
        $todos = 'query ($p: ID!, $m: ID, $s: ItemStatus) { todos(projectId: $p, milestoneId: $m, status: $s) { id } }';

        $this->as('member')->gql($todos, ['p' => $this->project->id, 'm' => null, 's' => null])
            ->assertJsonCount(2, 'data.todos');
        $this->as('member')->gql($todos, ['p' => $this->project->id, 'm' => $this->milestone->id, 's' => 'DONE'])
            ->assertJsonPath('data.todos', [['id' => (string) $doneTodo->id]]);

        $this->as('member')->gql('query ($s: TaskStatus) { myTasks(status: $s) { id } }', ['s' => null])
            ->assertJsonCount(2, 'data.myTasks');
        $this->as('member')->gql('query ($s: TaskStatus) { myTasks(status: $s) { id } }', ['s' => 'DONE'])
            ->assertJsonCount(1, 'data.myTasks');
    }

    public function test_my_tasks_and_dashboard(): void
    {
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id, 'due_date' => '2026-12-01'])->save();
        Task::factory()->for($this->todo)->create(['assigned_user_id' => $this->users['member']->id, 'status' => TaskStatus::DONE]);
        $this->todo->update(['due_date' => '2026-11-30']);

        $this->as('member')->gql('{ myTasks(status: OPEN) { id dueDate } }')
            ->assertJsonPath('data.myTasks', [['id' => (string) $this->task->id, 'dueDate' => '2026-12-01']]);

        $this->as('member')->gql('{ dashboard {
            upcomingTodos(limit: 3) { id dueDate }
            myOpenTasks { id }
            projects { name }
        } }')
            ->assertJsonPath('data.dashboard.upcomingTodos', [['id' => (string) $this->todo->id, 'dueDate' => '2026-11-30']])
            ->assertJsonPath('data.dashboard.myOpenTasks', [['id' => (string) $this->task->id]])
            ->assertJsonPath('data.dashboard.projects', [['name' => 'Mollo Planer']]);
    }

    public function test_dashboard_limit_is_validated(): void
    {
        $this->as('member')->gql('{ dashboard { upcomingTodos(limit: 51) { id } } }')
            ->assertGraphQLValidationKeys(['limit']);
    }

    public function test_user_list_only_for_user_managers(): void
    {
        $this->as('admin')->gql('{ users { name } }')->assertJsonCount(count($this->users), 'data.users');
        $this->assertUnauthorized($this->as('manager')->gql('{ users { name } }'));
        $this->assertUnauthorized($this->as('projektleitung')->gql('{ users { name } }'));
        $this->assertUnauthorized($this->as('member')->gql('{ users { name } }'));
    }

    public function test_user_projects_respect_the_viewer(): void
    {
        Project::factory()->withMember($this->users['owner'])->create(['name' => 'Secret']);

        $this->as('member')->gql('query ($id: ID!) { project(id: $id) { members { user { name projects { name } } } } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.members.0.user.projects', [['name' => 'Mollo Planer']]);
    }
}
