<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\GraphQL\Mutations\ResetUserTwoFactor;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

class MutationTest extends GraphQLTestCase
{
    // ─── Projects ────────────────────────────────────────────────

    public function test_admins_create_projects_and_become_members_with_the_creator_role(): void
    {
        $mutation = 'mutation { createProject(input: {name: "Neu", locale: EN}) { name locale viewerRole { key } } }';

        $this->assertUnauthorized($this->as('owner')->gql($mutation));

        $this->as('admin')->gql($mutation)
            ->assertJsonPath('data.createProject', ['name' => 'Neu', 'locale' => 'EN', 'viewerRole' => ['key' => 'owner']]);
    }

    public function test_project_has_an_icon_that_follows_the_icon_rule(): void
    {
        $this->as('admin')->gql('mutation { createProject(input: {name: "Podcast", icon: "microphone"}) { icon } }')
            ->assertJsonPath('data.createProject.icon', 'microphone');

        $this->as('manager')->gql('mutation ($id: ID!) { updateProject(id: $id, input: {icon: "globe"}) { icon } }', ['id' => $this->project->id])
            ->assertJsonPath('data.updateProject.icon', 'globe');

        $this->as('manager')->gql('mutation ($id: ID!) { updateProject(id: $id, input: {icon: "fa-solid fa-globe"}) { icon } }', ['id' => $this->project->id])
            ->assertGraphQLValidationKeys(['input.icon']);

        $this->as('manager')->gql('mutation ($id: ID!) { updateProject(id: $id, input: {icon: null}) { icon } }', ['id' => $this->project->id])
            ->assertJsonPath('data.updateProject.icon', null);
    }

    public function test_update_and_delete_project_follow_the_table(): void
    {
        $update = 'mutation ($id: ID!) { updateProject(id: $id, input: {locale: EN}) { locale } }';
        $delete = 'mutation ($id: ID!) { deleteProject(id: $id) { id } }';
        $id = ['id' => $this->project->id];

        $this->assertUnauthorized($this->as('member')->gql($update, $id));
        $this->as('manager')->gql($update, $id)->assertJsonPath('data.updateProject.locale', 'EN');

        $this->assertUnauthorized($this->as('manager')->gql($delete, $id));
        $this->as('owner')->gql($delete, $id)->assertJsonPath('data.deleteProject.id', (string) $this->project->id);
        $this->assertModelMissing($this->project);
    }

    // ─── Members ─────────────────────────────────────────────────

    public function test_manager_adds_members_but_cannot_grant_owner(): void
    {
        $newcomer = User::factory()->create();
        $set = 'mutation ($p: ID!, $u: ID!, $r: String!) { setProjectMember(projectId: $p, userId: $u, role: $r) { members { user { id } role { key } } } }';

        $this->as('manager')->gql($set, ['p' => $this->project->id, 'u' => $newcomer->id, 'r' => 'MEMBER'])
            ->assertJsonMissingPath('errors');
        $this->assertSame(ProjectRole::MEMBER->value, $this->project->roleOf($newcomer)?->key);

        $this->assertUnauthorized($this->as('manager')->gql($set, ['p' => $this->project->id, 'u' => $this->users['manager']->id, 'r' => 'OWNER']));
        $this->assertUnauthorized($this->as('manager')->gql($set, ['p' => $this->project->id, 'u' => $this->users['owner']->id, 'r' => 'MEMBER']));
        $this->assertUnauthorized($this->as('member')->gql($set, ['p' => $this->project->id, 'u' => $newcomer->id, 'r' => 'MANAGER']));
    }

    public function test_owner_grants_owner_and_last_owner_is_protected(): void
    {
        $set = 'mutation ($p: ID!, $u: ID!, $r: String!) { setProjectMember(projectId: $p, userId: $u, role: $r) { id } }';
        $remove = 'mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }';
        $owner = $this->users['owner'];

        $this->as('owner')->gql($set, ['p' => $this->project->id, 'u' => $owner->id, 'r' => 'MANAGER'])
            ->assertGraphQLValidationKeys(['role']);
        $this->as('owner')->gql($remove, ['p' => $this->project->id, 'u' => $owner->id])
            ->assertGraphQLValidationKeys(['userId']);

        $this->as('owner')->gql($set, ['p' => $this->project->id, 'u' => $this->users['manager']->id, 'r' => 'OWNER'])
            ->assertJsonMissingPath('errors');
        $this->as('owner')->gql($remove, ['p' => $this->project->id, 'u' => $owner->id])
            ->assertJsonMissingPath('errors');
        $this->assertNull($this->project->roleOf($owner));
    }

    public function test_admin_may_not_remove_the_last_owner_either(): void
    {
        $this->as('admin')->gql('mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', [
            'p' => $this->project->id, 'u' => $this->users['owner']->id,
        ])->assertGraphQLValidationKeys(['userId']);

        $this->assertSame(1, $this->project->members()->wherePivot('role_id', Role::idOf(Role::SCOPE_PROJECT, ProjectRole::OWNER))->count());
    }

    public function test_removed_member_loses_task_assignments_in_this_project(): void
    {
        $member = $this->users['member'];
        $this->task->forceFill(['assigned_user_id' => $member->id])->save();
        $elsewhere = Task::factory()->create(['assigned_user_id' => $member->id]);

        $this->as('manager')->gql('mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', [
            'p' => $this->project->id, 'u' => $member->id,
        ])->assertJsonMissingPath('errors');

        $this->assertNull($this->task->fresh()->assigned_user_id);
        $this->assertSame($member->id, $elsewhere->fresh()->assigned_user_id);
    }

    // ─── Milestones and todos ────────────────────────────────────

    public function test_member_creates_milestone_with_rich_text_and_icon(): void
    {
        $this->as('member')->gql('mutation ($in: CreateMilestoneInput!) { createMilestone(input: $in) { title description icon dueDate status } }', ['in' => [
            'projectId' => $this->project->id,
            'title' => 'Beta',
            'description' => $this->doc('Ziel'),
            'icon' => 'rocket-launch',
            'dueDate' => '2026-12-24',
        ]])->assertJsonPath('data.createMilestone', [
            'title' => 'Beta',
            'description' => $this->doc('Ziel'),
            'icon' => 'rocket-launch',
            'dueDate' => '2026-12-24',
            'status' => 'OPEN',
        ]);
    }

    public function test_rich_text_and_icon_errors_appear_per_field(): void
    {
        $this->as('member')->gql('mutation ($in: CreateMilestoneInput!) { createMilestone(input: $in) { id } }', ['in' => [
            'projectId' => $this->project->id,
            'title' => 'Beta',
            'description' => ['type' => 'doc', 'content' => [['type' => 'image']]],
            'icon' => 'fa-solid fa-rocket',
        ]])->assertGraphQLValidationKeys(['input.description', 'input.icon']);
    }

    public function test_empty_description_is_stored_as_null(): void
    {
        $this->as('member')->gql('mutation ($id: ID!, $in: UpdateTodoInput!) { updateTodo(id: $id, input: $in) { description } }', [
            'id' => $this->todo->id,
            'in' => ['description' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]],
        ])->assertJsonPath('data.updateTodo.description', null);
    }

    public function test_status_is_not_an_input(): void
    {
        $this->as('member')->gql('mutation ($id: ID!) { updateTodo(id: $id, input: {status: DONE}) { status } }', ['id' => $this->todo->id])
            ->assertGraphQLErrorMessage('Field "status" is not defined by type "UpdateTodoInput".');
    }

    public function test_member_cannot_delete_todos_or_milestones(): void
    {
        $this->assertUnauthorized($this->as('member')->gql('mutation ($id: ID!) { deleteTodo(id: $id) { id } }', ['id' => $this->todo->id]));
        $this->assertUnauthorized($this->as('member')->gql('mutation ($id: ID!) { deleteMilestone(id: $id) { id } }', ['id' => $this->milestone->id]));
        $this->as('manager')->gql('mutation ($id: ID!) { deleteTodo(id: $id) { id } }', ['id' => $this->todo->id])->assertJsonMissingPath('errors');
    }

    public function test_stranger_cannot_create_in_foreign_project(): void
    {
        $this->assertUnauthorized($this->as('stranger')->gql('mutation ($p: ID!) { createMilestone(input: {projectId: $p, title: "Hack"}) { id } }', ['p' => $this->project->id]));
    }

    // ─── Tasks ───────────────────────────────────────────────────

    public function test_manager_creates_task_at_end_of_open_column(): void
    {
        $create = 'mutation ($in: CreateTaskInput!) { createTask(input: $in) { status boardPosition assignee { name } } }';
        $input = ['todoId' => $this->todo->id, 'title' => 'Neuer Task', 'assigneeId' => $this->users['member']->id];

        $this->as('manager')->gql($create, ['in' => $input])
            ->assertJsonPath('data.createTask', ['status' => 'OPEN', 'boardPosition' => 1, 'assignee' => ['name' => 'David Member']]);
    }

    public function test_create_task_takes_the_commit_from_the_input(): void
    {
        $this->as('manager')->gql('mutation ($in: CreateTaskInput!) { createTask(input: $in) { commit } }', ['in' => [
            'todoId' => $this->todo->id, 'title' => 'Mit Commit', 'commit' => 'a1b2c3d',
        ]])->assertJsonPath('data.createTask.commit', 'a1b2c3d');
    }

    public function test_task_cannot_be_assigned_to_people_who_cannot_become_members(): void
    {
        // Non-members join when someone with members.manage assigns them (AssignNonMemberTest);
        // a person who is not approved never can.
        $pending = User::factory()->unapproved()->create();

        $this->as('manager')->gql('mutation ($in: CreateTaskInput!) { createTask(input: $in) { id } }', ['in' => [
            'todoId' => $this->todo->id, 'title' => 'Neuer Task', 'assigneeId' => $pending->id,
        ]])->assertGraphQLValidationKeys(['input.assigneeId']);

        $this->as('manager')->gql('mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { id } }', [
            'id' => $this->task->id, 'u' => $pending->id,
        ])->assertGraphQLValidationKeys(['assigneeId']);
    }

    public function test_assign_and_unassign(): void
    {
        $assign = 'mutation ($id: ID!, $u: ID) { assignTask(id: $id, assigneeId: $u) { assignee { name } } }';

        $this->as('owner')->gql($assign, ['id' => $this->task->id, 'u' => $this->users['member']->id])
            ->assertJsonPath('data.assignTask.assignee.name', 'David Member');
        $this->as('owner')->gql($assign, ['id' => $this->task->id, 'u' => null])
            ->assertJsonPath('data.assignTask.assignee', null);
        $this->assertUnauthorized($this->as('member')->gql($assign, ['id' => $this->task->id, 'u' => $this->users['owner']->id]));
    }

    public function test_move_reorders_columns_and_updates_status(): void
    {
        $second = Task::factory()->for($this->todo)->create(['board_position' => 1]);
        $done = Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE, 'board_position' => 0]);
        $move = 'mutation ($id: ID!, $s: TaskStatus!, $p: Int!) { moveTask(id: $id, status: $s, position: $p) { status boardPosition } }';

        // Member moves the first open task to the top of DONE.
        $this->as('member')->gql($move, ['id' => $this->task->id, 's' => 'DONE', 'p' => 0])
            ->assertJsonPath('data.moveTask', ['status' => 'DONE', 'boardPosition' => 0]);

        $this->assertSame(1, $done->fresh()->board_position);
        $this->assertSame(0, $second->fresh()->board_position);
        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);

        $this->as('member')->gql($move, ['id' => $second->id, 's' => 'DONE', 'p' => 99])
            ->assertJsonPath('data.moveTask', ['status' => 'DONE', 'boardPosition' => 2]);
        $this->assertSame(ItemStatus::DONE, $this->todo->fresh()->status);

        $this->as('member')->gql($move, ['id' => $second->id, 's' => 'DONE', 'p' => -1])
            ->assertGraphQLValidationKeys(['position']);
        $this->assertUnauthorized($this->as('stranger')->gql($move, ['id' => $second->id, 's' => 'OPEN', 'p' => 0]));
    }

    public function test_move_to_todo_keeps_the_task_in_its_project_and_updates_statuses(): void
    {
        $this->task->forceFill(['status' => TaskStatus::DONE])->save();
        $open = Task::factory()->for($this->todo)->create(['board_position' => 1]);
        $target = Todo::factory()->for(Milestone::factory()->for($this->project))->create();
        $move = 'mutation ($id: ID!, $todo: ID!) { moveTaskToTodo(id: $id, todoId: $todo) { todo { id milestone { id } } status boardPosition } }';

        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);

        // Like changing a task: managers yes, members no.
        $this->assertUnauthorized($this->as('member')->gql($move, ['id' => $open->id, 'todo' => $target->id]));

        $this->as('manager')->gql($move, ['id' => $open->id, 'todo' => $target->id])
            ->assertJsonPath('data.moveTaskToTodo', [
                'todo' => ['id' => (string) $target->id, 'milestone' => ['id' => (string) $target->milestone_id]],
                'status' => 'OPEN',
                'boardPosition' => 1,
            ]);

        // The old todo has only its done task left, the new one an open task.
        $this->assertSame(ItemStatus::DONE, $this->todo->fresh()->status);
        $this->assertSame(ItemStatus::OPEN, $target->fresh()->status);

        // Not into a todo of another project, nor into one that does not exist.
        $foreign = Todo::factory()->create();
        $this->as('owner')->gql($move, ['id' => $open->id, 'todo' => $foreign->id])
            ->assertGraphQLValidationKeys(['todoId']);
        $this->as('owner')->gql($move, ['id' => $open->id, 'todo' => 999999])
            ->assertGraphQLValidationKeys(['todoId']);
        $this->assertSame($target->id, $open->fresh()->todo_id);
    }

    public function test_member_cannot_update_or_delete_tasks(): void
    {
        $this->assertUnauthorized($this->as('member')->gql('mutation ($id: ID!) { updateTask(id: $id, input: {title: "Neu"}) { id } }', ['id' => $this->task->id]));
        $this->assertUnauthorized($this->as('member')->gql('mutation ($id: ID!) { deleteTask(id: $id) { id } }', ['id' => $this->task->id]));
        $this->as('manager')->gql('mutation ($id: ID!) { updateTask(id: $id, input: {title: "Neu", icon: "bug"}) { title icon } }', ['id' => $this->task->id])
            ->assertJsonPath('data.updateTask', ['title' => 'Neu', 'icon' => 'bug']);
    }

    // ─── Comments ────────────────────────────────────────────────

    public function test_author_is_taken_from_login(): void
    {
        $this->as('member')->gql('mutation ($in: AddCommentInput!) { addComment(input: $in) { author { name } body viewerCanEdit } }', ['in' => [
            'taskId' => $this->task->id, 'body' => $this->doc('Erledigt?'),
        ]])->assertJsonPath('data.addComment', ['author' => ['name' => 'David Member'], 'body' => $this->doc('Erledigt?'), 'viewerCanEdit' => true]);
    }

    public function test_empty_comment_is_rejected(): void
    {
        $this->as('member')->gql('mutation ($in: AddCommentInput!) { addComment(input: $in) { id } }', ['in' => [
            'taskId' => $this->task->id, 'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ]])->assertGraphQLValidationKeys(['input.body']);

        $this->assertSame(0, Comment::count());
    }

    public function test_comment_edit_and_delete_rules(): void
    {
        $comment = Comment::factory()->for($this->task)->for($this->users['member'], 'author')->create();
        $update = 'mutation ($id: ID!, $in: UpdateCommentInput!) { updateComment(id: $id, input: $in) { body } }';
        $delete = 'mutation ($id: ID!) { deleteComment(id: $id) { id } }';

        $this->assertUnauthorized($this->as('admin')->gql($update, ['id' => $comment->id, 'in' => ['body' => $this->doc('Admin')]]));
        $this->as('member')->gql($update, ['id' => $comment->id, 'in' => ['body' => $this->doc('Neu')]])
            ->assertJsonPath('data.updateComment.body', $this->doc('Neu'));

        $this->as('manager')->gql($delete, ['id' => $comment->id])->assertJsonMissingPath('errors');
        $this->assertModelMissing($comment);
    }

    // ─── Users ───────────────────────────────────────────────────

    public function test_update_my_notifications_only_changes_the_viewer(): void
    {
        $this->as('member')->gql('mutation { updateMyNotifications(emailEnabled: false) { id notifyByEmail } }')
            ->assertJsonPath('data.updateMyNotifications', ['id' => (string) $this->users['member']->id, 'notifyByEmail' => false]);

        $this->assertTrue($this->users['owner']->fresh()->notify_by_email);
    }

    public function test_admin_creates_users(): void
    {
        $create = 'mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { name role { key } } }';

        $this->assertUnauthorized($this->as('owner')->gql($create));
        $this->as('admin')->gql($create)->assertJsonPath('data.createUser', ['name' => 'Fritz', 'role' => ['key' => 'mitglied']]);
        $this->as('admin')->gql($create)->assertGraphQLValidationKeys(['input.email']);
    }

    public function test_update_user_rules(): void
    {
        $update = 'mutation ($id: ID!, $in: UpdateUserInput!) { updateUser(id: $id, input: $in) { email role { key } } }';
        $admin = $this->users['admin'];

        $this->as('admin')->gql($update, ['id' => $this->users['member']->id, 'in' => ['role' => 'admin']])
            ->assertJsonPath('data.updateUser.role.key', 'admin');

        $this->as('admin')->gql($update, ['id' => $this->users['member']->id, 'in' => ['email' => $this->users['owner']->email]])
            ->assertGraphQLValidationKeys(['input.email']);

        $this->as('admin')->gql($update, ['id' => $admin->id, 'in' => ['email' => $admin->email]])
            ->assertJsonMissingPath('errors');

        $this->assertUnauthorized($this->as('admin')->gql($update, ['id' => $admin->id, 'in' => ['role' => 'mitglied']]));
        $this->assertSame(UserRole::ADMIN->value, $admin->fresh()->role?->key);

        $this->assertUnauthorized($this->as('owner')->gql($update, ['id' => $this->users['member']->id, 'in' => ['name' => 'Xaver']]));
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $delete = 'mutation ($id: ID!) { deleteUser(id: $id) { id } }';

        $this->assertUnauthorized($this->as('admin')->gql($delete, ['id' => $this->users['admin']->id]));
        $this->as('admin')->gql($delete, ['id' => $this->users['stranger']->id])->assertJsonMissingPath('errors');
    }

    public function test_reset_two_factor_removes_factors_and_sessions(): void
    {
        $member = $this->users['member'];
        $member->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now(), 'remember_token' => 'lost-device'])->save();
        DB::table('passkeys')->insert(['user_id' => $member->id, 'name' => 'Phone', 'credential_id' => 'c1', 'credential' => '{}']);

        $this->as('admin')->gql('mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }', ['id' => $member->id])
            ->assertJsonMissingPath('errors');

        $member->refresh();
        $this->assertNull($member->two_factor_secret);
        $this->assertNull($member->two_factor_confirmed_at);
        $this->assertFalse($member->hasPasskeysEnabled());
        $this->assertNotSame('lost-device', $member->remember_token);

        $this->assertUnauthorized($this->as('owner')->gql('mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }', ['id' => $member->id]));
    }

    public function test_reset_two_factor_ends_database_sessions(): void
    {
        // Tests use the array session driver; production uses the database.
        config(['session.driver' => 'database']);
        $member = $this->users['member'];
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $this->users['owner']->id, 'payload' => '', 'last_activity' => time()]);

        (new ResetUserTwoFactor)(null, ['id' => $member->id]);

        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());
    }

    // ─── Transactions ────────────────────────────────────────────

    /**
     * Lighthouse wraps only @create, @update and @delete in a transaction.
     * Mutations with their own resolver must do it themselves, otherwise a
     * failure leaves half the changes behind and row locks do nothing.
     *
     * @param  \Closure(self): array<string, mixed>  $variables
     */
    #[DataProvider('customMutations')]
    public function test_custom_mutations_write_inside_a_transaction(string $actor, string $mutation, \Closure $variables): void
    {
        Notification::fake();
        $variables = $variables($this);
        $levelOutside = DB::transactionLevel();
        $writesOutside = [];
        DB::listen(function (QueryExecuted $query) use ($levelOutside, &$writesOutside): void {
            // The planer revision is counted on purpose after the commit (App\Planer\PlanerRevision).
            $countsRevision = str_contains($query->sql, 'planer_revision');
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql) && $query->connection->transactionLevel() <= $levelOutside && ! $countsRevision) {
                $writesOutside[] = $query->sql;
            }
        });

        $this->as($actor)->gql($mutation, $variables)->assertJsonMissingPath('errors');

        $this->assertSame([], $writesOutside);
    }

    /**
     * @return array<string, array{string, string, \Closure(self): array<string, mixed>}>
     */
    public static function customMutations(): array
    {
        return [
            'createProject' => ['admin', 'mutation { createProject(input: {name: "Neu"}) { id } }', fn (self $test) => []],
            'importProjectPlan' => ['admin', 'mutation ($u: String!) { importProjectPlan(plan: {name: "Plan", members: [{email: $u, role: OWNER}], milestones: [{title: "Start", todos: [{title: "Vorbereiten", tasks: [{title: "Los", assigneeEmail: $u}]}]}]}) { id } }', fn (self $test) => ['u' => $test->users['member']->email]],
            'createUser' => ['admin', 'mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { id } }', fn (self $test) => []],
            'resetUserTwoFactor' => ['admin', 'mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }', fn (self $test) => ['id' => $test->users['member']->id]],
            'createApiToken' => ['member', 'mutation { createApiToken(name: "Claude") { plainTextToken } }', fn (self $test) => []],
            'updateAppSettings' => ['admin', 'mutation { updateAppSettings(twoFactorEnabled: false) { twoFactorEnabled } }', fn (self $test) => []],
            'deleteUserAvatar' => ['admin', 'mutation ($id: ID!) { deleteUserAvatar(id: $id) { id } }', fn (self $test) => ['id' => $test->users['member']->id]],
            'updateMyNotifications' => ['member', 'mutation { updateMyNotifications(emailEnabled: false) { id } }', fn (self $test) => []],
            'setProjectMember' => ['owner', 'mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "member") { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['stranger']->id]],
            'removeProjectMember' => ['owner', 'mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['member']->id]],
            'createTask' => ['manager', 'mutation ($t: ID!, $u: ID!) { createTask(input: {todoId: $t, title: "Neu", assigneeId: $u}) { id } }', fn (self $test) => ['t' => $test->todo->id, 'u' => $test->users['member']->id]],
            'assignTask' => ['manager', 'mutation ($id: ID!, $u: ID!) { assignTask(id: $id, assigneeId: $u) { id } }', fn (self $test) => ['id' => $test->task->id, 'u' => $test->users['member']->id]],
            'moveTask' => ['member', 'mutation ($id: ID!) { moveTask(id: $id, status: DONE, position: 0) { id } }', fn (self $test) => ['id' => $test->task->id]],
            'moveTaskToTodo' => ['manager', 'mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { id } }', fn (self $test) => ['id' => $test->task->id, 't' => Todo::factory()->for($test->milestone)->create()->id]],
            'addComment' => ['member', 'mutation ($t: ID!, $body: RichText!) { addComment(input: {taskId: $t, body: $body}) { id } }', fn (self $test) => ['t' => $test->task->id, 'body' => $test->doc('Hallo')]],
            'createRole' => ['admin', 'mutation ($f: ID!) { createRole(input: {scope: PROJECT, names: {de: "Prüferin", en: "Reviewer"}, functionIds: [$f]}) { id } }', fn (self $test) => ['f' => self::functionId('contribute')]],
            'updateRole' => ['admin', 'mutation ($id: ID!, $f: ID!) { updateRole(id: $id, input: {names: {de: "Gast", en: "Guest"}, functionIds: [$f]}) { id } }', fn (self $test) => ['id' => self::roleId('guest'), 'f' => self::functionId('contribute')]],
            'duplicateRole' => ['admin', 'mutation ($id: ID!) { duplicateRole(id: $id) { id } }', fn (self $test) => ['id' => self::roleId('manager')]],
            'deleteRole' => ['admin', 'mutation ($id: ID!) { deleteRole(id: $id) { id } }', fn (self $test) => ['id' => DB::table('roles')->insertGetId(['scope' => 'project', 'key' => 'own', 'names' => '{"de":"Eigene","en":"Own"}'])]],
            'setProjectCreatorRole' => ['admin', 'mutation ($key: String!) { setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key } } }', fn (self $test) => ['key' => 'owner']],
            'setDefaultRole' => ['admin', 'mutation ($id: ID!) { setDefaultRole(id: $id) { id } }', fn (self $test) => ['id' => self::roleId('guest')]],
            'resetRole' => ['admin', 'mutation ($id: ID!) { resetRole(id: $id) { id } }', fn (self $test) => ['id' => self::roleId('manager')]],
            'createRoleFunction' => ['admin', 'mutation { createRoleFunction(input: {scope: PROJECT, names: {de: "Aufräumen", en: "Tidy up"}, rights: [TASK_DELETE]}) { id } }', fn (self $test) => []],
            'updateRoleFunction' => ['admin', 'mutation ($id: ID!) { updateRoleFunction(id: $id, input: {names: {de: "Planen", en: "Plan"}, rights: [TODO_CREATE]}) { id } }', fn (self $test) => ['id' => self::functionId('plan')]],
            'duplicateRoleFunction' => ['admin', 'mutation ($id: ID!) { duplicateRoleFunction(id: $id) { id } }', fn (self $test) => ['id' => self::functionId('plan')]],
            'deleteRoleFunction' => ['admin', 'mutation ($id: ID!) { deleteRoleFunction(id: $id) { id } }', fn (self $test) => ['id' => DB::table('functions')->insertGetId(['scope' => 'project', 'key' => 'own', 'names' => '{"de":"Eigene","en":"Own"}'])]],
            'resetRoleFunction' => ['admin', 'mutation ($id: ID!) { resetRoleFunction(id: $id) { id } }', fn (self $test) => ['id' => self::functionId('plan')]],
            'deleteApiToken' => ['owner', 'mutation ($id: ID!) { deleteApiToken(id: $id) { name } }', fn (self $test) => ['id' => McpTokens::create($test->users['owner'], 'Meins')->accessToken->id]],
        ];
    }

    private static function roleId(string $key): int
    {
        return (int) DB::table('roles')->where(['scope' => 'project', 'key' => $key])->value('id');
    }

    private static function functionId(string $key): int
    {
        return (int) DB::table('functions')->where(['scope' => 'project', 'key' => $key])->value('id');
    }
}
