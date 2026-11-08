<?php

namespace Tests\Feature\GraphQL;

use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every operation is wired to its policy (@can) or its read scope. The
 * policies themselves are tested in PolicyTest; here a missing directive
 * in the schema would show up.
 */
class OutsiderAccessTest extends GraphQLTestCase
{
    /**
     * Tables a refused mutation must leave unchanged.
     */
    private const TABLES = ['users', 'projects', 'project_user', 'milestones', 'todos', 'tasks', 'comments', 'app_settings'];

    private Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comment = Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create();
    }

    /**
     * @param  \Closure(self): array<string, mixed>  $variables
     */
    #[DataProvider('refusedMutations')]
    public function test_mutation_is_refused_and_changes_nothing(string $actor, string $mutation, \Closure $variables): void
    {
        $before = $this->snapshot();

        $this->assertUnauthorized($this->as($actor)->gql($mutation, $variables($this)));

        $this->assertEquals($before, $this->snapshot());
    }

    /**
     * Project mutations tried by someone outside the project, admin
     * mutations tried by a project owner who is not an admin.
     *
     * @return array<string, array{string, string, \Closure(self): array<string, mixed>}>
     */
    public static function refusedMutations(): array
    {
        $project = fn (self $test) => ['id' => $test->project->id];
        $milestone = fn (self $test) => ['id' => $test->milestone->id];
        $todo = fn (self $test) => ['id' => $test->todo->id];
        $task = fn (self $test) => ['id' => $test->task->id];
        $comment = fn (self $test) => ['id' => $test->comment->id];
        $member = fn (self $test) => ['id' => $test->users['member']->id];

        return [
            'updateProject' => ['stranger', 'mutation ($id: ID!) { updateProject(id: $id, input: {name: "Hack"}) { id } }', $project],
            'deleteProject' => ['stranger', 'mutation ($id: ID!) { deleteProject(id: $id) { id } }', $project],
            'setProjectMember (join as owner)' => ['stranger', 'mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: OWNER) { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['stranger']->id]],
            'removeProjectMember' => ['stranger', 'mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['member']->id]],
            'createMilestone' => ['stranger', 'mutation ($p: ID!) { createMilestone(input: {projectId: $p, title: "Hack"}) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'updateMilestone' => ['stranger', 'mutation ($id: ID!) { updateMilestone(id: $id, input: {title: "Hack"}) { id } }', $milestone],
            'deleteMilestone' => ['stranger', 'mutation ($id: ID!) { deleteMilestone(id: $id) { id } }', $milestone],
            'createTodo' => ['stranger', 'mutation ($m: ID!) { createTodo(input: {milestoneId: $m, title: "Hack"}) { id } }', fn (self $test) => ['m' => $test->milestone->id]],
            'updateTodo' => ['stranger', 'mutation ($id: ID!) { updateTodo(id: $id, input: {title: "Hack"}) { id } }', $todo],
            'deleteTodo' => ['stranger', 'mutation ($id: ID!) { deleteTodo(id: $id) { id } }', $todo],
            'createTask' => ['stranger', 'mutation ($t: ID!) { createTask(input: {todoId: $t, title: "Hack"}) { id } }', fn (self $test) => ['t' => $test->todo->id]],
            'updateTask' => ['stranger', 'mutation ($id: ID!) { updateTask(id: $id, input: {title: "Hack"}) { id } }', $task],
            'assignTask' => ['stranger', 'mutation ($id: ID!, $u: ID!) { assignTask(id: $id, assigneeId: $u) { id } }', fn (self $test) => ['id' => $test->task->id, 'u' => $test->users['member']->id]],
            'moveTask' => ['stranger', 'mutation ($id: ID!) { moveTask(id: $id, status: DONE, position: 0) { id } }', $task],
            'deleteTask' => ['stranger', 'mutation ($id: ID!) { deleteTask(id: $id) { id } }', $task],
            'addComment' => ['stranger', 'mutation ($t: ID!, $b: RichText!) { addComment(input: {taskId: $t, body: $b}) { id } }', fn (self $test) => ['t' => $test->task->id, 'b' => $test->doc('Hack')]],
            'updateComment' => ['stranger', 'mutation ($id: ID!, $b: RichText!) { updateComment(id: $id, input: {body: $b}) { id } }', fn (self $test) => ['id' => $test->comment->id, 'b' => $test->doc('Hack')]],
            'deleteComment' => ['stranger', 'mutation ($id: ID!) { deleteComment(id: $id) { id } }', $comment],
            'createProject' => ['owner', 'mutation { createProject(input: {name: "Eigenes"}) { id } }', fn (self $test) => []],
            'createUser' => ['owner', 'mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { id } }', fn (self $test) => []],
            'updateUser (make self admin)' => ['owner', 'mutation ($id: ID!) { updateUser(id: $id, input: {isAdmin: true}) { id } }', fn (self $test) => ['id' => $test->users['owner']->id]],
            'resetUserTwoFactor' => ['owner', 'mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }', $member],
            'deleteUser' => ['owner', 'mutation ($id: ID!) { deleteUser(id: $id) { id } }', $member],
            'updateAppSettings (2FA off)' => ['owner', 'mutation { updateAppSettings(twoFactorEnabled: false) { twoFactorEnabled } }', fn (self $test) => []],
            'deleteUserAvatar (someone else)' => ['owner', 'mutation ($id: ID!) { deleteUserAvatar(id: $id) { id } }', $member],
        ];
    }

    #[DataProvider('hiddenQueries')]
    public function test_query_shows_nothing_of_a_foreign_project(string $query, string $path, mixed $expected): void
    {
        $variables = [
            'project' => $this->project->id,
            'milestone' => $this->milestone->id,
            'todo' => $this->todo->id,
            'task' => $this->task->id,
        ];
        preg_match_all('/\$(\w+)/', $query, $used);

        $this->as('stranger')->gql($query, array_intersect_key($variables, array_flip($used[1])))
            ->assertJsonMissingPath('errors')
            ->assertJsonPath($path, $expected);
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function hiddenQueries(): array
    {
        return [
            'project' => ['query ($project: ID!) { project(id: $project) { id } }', 'data.project', null],
            'milestone' => ['query ($milestone: ID!) { milestone(id: $milestone) { id } }', 'data.milestone', null],
            'todo' => ['query ($todo: ID!) { todo(id: $todo) { id } }', 'data.todo', null],
            'task' => ['query ($task: ID!) { task(id: $task) { id } }', 'data.task', null],
            'projects' => ['{ projects { id } }', 'data.projects', []],
            'milestones' => ['query ($project: ID!) { milestones(projectId: $project) { id } }', 'data.milestones', []],
            'todos' => ['query ($project: ID!) { todos(projectId: $project) { id } }', 'data.todos', []],
            'kanban' => ['query ($project: ID!) { kanban(projectId: $project) { status } }', 'data.kanban', []],
            'dashboard' => ['{ dashboard { projects { id } upcomingTodos { id } myOpenTasks { id } } }', 'data.dashboard', ['projects' => [], 'upcomingTodos' => [], 'myOpenTasks' => []]],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        return collect(self::TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all()])
            ->all();
    }
}
