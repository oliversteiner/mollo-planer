<?php

namespace Tests\Feature\GraphQL;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Template;
use App\Services\Deletion;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The mutations an archived project refuses (rollen-funktionen-rechte.md
 * §7.7: archived means read only). Every mutation of the schema is either
 * one of the cases below or excluded with a reason, so a new mutation gets
 * a decision here. Each case is sent in an archived project by the owner
 * and by an admin (projects.manageAny), and must be refused with the
 * message for the archive.
 */
class ArchivedProjectMutationsTest extends GraphQLTestCase
{
    /** Not a write into one project, or allowed in the archive. */
    private const EXCLUDED = [
        'archiveProject' => 'allowed in the archive (project.archive)',
        'unarchiveProject' => 'brings the project back (project.archive)',
        'restoreFromTrash' => 'allowed in the archive (trash.restore, §7.7)',
        'approveUser' => 'user administration',
        'setProjectCreatorRole' => 'app settings, belong to no project',
        'createApiToken' => 'personal token',
        'createTemplate' => 'templates belong to no project',
        'createTemplateFromFile' => 'templates belong to no project',
        'createTemplateFromProject' => 'reads the project (planer.export holds in the archive), writes a template',
        'createProject' => 'a new project is not archived',
        'createRole' => 'roles and functions belong to no project',
        'createRoleFunction' => 'roles and functions belong to no project',
        'createUser' => 'user administration',
        'deleteApiToken' => 'personal token',
        'deleteTemplate' => 'templates belong to no project',
        'deleteRole' => 'roles and functions belong to no project',
        'deleteRoleFunction' => 'roles and functions belong to no project',
        'deleteUser' => 'user administration',
        'deleteUserAvatar' => 'user profile',
        'draftProjectPlan' => 'writes nothing',
        'duplicateTemplate' => 'templates belong to no project',
        'duplicateRole' => 'roles and functions belong to no project',
        'duplicateRoleFunction' => 'roles and functions belong to no project',
        'importProjectPlan' => 'creates a new project',
        'rejectUser' => 'user administration',
        'resetRole' => 'roles and functions belong to no project',
        'resetRoleFunction' => 'roles and functions belong to no project',
        'resetUserTwoFactor' => 'user administration',
        'setDefaultRole' => 'roles and functions belong to no project',
        'updateAppSettings' => 'application settings',
        'updateTemplate' => 'templates belong to no project',
        'updateMyAvatar' => 'user profile',
        'updateMyNotifications' => 'user profile',
        'updateRole' => 'roles and functions belong to no project',
        'updateRoleFunction' => 'roles and functions belong to no project',
        'updateUser' => 'user administration',
    ];

    /** Tables a refused mutation must leave unchanged. */
    private const TABLES = ['projects', 'project_user', 'milestones', 'todos', 'tasks', 'comments', 'label_task'];

    private Comment $comment;

    /** A task in the trash, deleted before the project was archived. */
    private string $deletionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comment = Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create();
        $this->deletionId = (string) app(Deletion::class)->delete(Task::factory()->for($this->todo)->create())->deletion_id;
        $this->project->forceFill(['archived_at' => now()])->save();
    }

    /**
     * @param  \Closure(self): array<string, mixed>  $variables
     */
    #[DataProvider('refusedWhenArchived')]
    public function test_the_owner_cannot_write_into_an_archived_project(string $mutation, \Closure $variables): void
    {
        $this->assertRefused('owner', $mutation, $variables);
    }

    /**
     * @param  \Closure(self): array<string, mixed>  $variables
     */
    #[DataProvider('refusedWhenArchived')]
    public function test_an_admin_cannot_write_into_an_archived_project_either(string $mutation, \Closure $variables): void
    {
        // Only the author edits a comment, archived or not.
        $message = $this->dataName() === 'updateComment' ? 'Dafür fehlt dir die Berechtigung.' : null;

        $this->assertRefused('admin', $mutation, $variables, $message);
    }

    /**
     * One case per mutation, keyed by its name, written as the owner (who is
     * also the author of the comment) would send it in an active project.
     *
     * @return array<string, array{string, \Closure(self): array<string, mixed>}>
     */
    public static function refusedWhenArchived(): array
    {
        $project = fn (self $test) => ['id' => $test->project->id];
        $milestone = fn (self $test) => ['id' => $test->milestone->id];
        $todo = fn (self $test) => ['id' => $test->todo->id];
        $task = fn (self $test) => ['id' => $test->task->id];

        return [
            'addComment' => ['mutation ($t: ID!, $b: RichText!) { addComment(input: {taskId: $t, body: $b}) { id } }', fn (self $test) => ['t' => $test->task->id, 'b' => $test->doc('Neu')]],
            'assignTask' => ['mutation ($id: ID!, $u: ID!) { assignTask(id: $id, assigneeId: $u) { id } }', fn (self $test) => ['id' => $test->task->id, 'u' => $test->users['member']->id]],
            'createMilestone' => ['mutation ($p: ID!) { createMilestone(input: {projectId: $p, title: "Neu"}) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'createTask' => ['mutation ($t: ID!) { createTask(input: {todoId: $t, title: "Neu"}) { id } }', fn (self $test) => ['t' => $test->todo->id]],
            'createTodo' => ['mutation ($m: ID!) { createTodo(input: {milestoneId: $m, title: "Neu"}) { id } }', fn (self $test) => ['m' => $test->milestone->id]],
            'deleteComment' => ['mutation ($id: ID!) { deleteComment(id: $id) { id } }', fn (self $test) => ['id' => $test->comment->id]],
            'deleteMilestone' => ['mutation ($id: ID!) { deleteMilestone(id: $id) { id } }', $milestone],
            'deleteProject' => ['mutation ($id: ID!) { deleteProject(id: $id) { id } }', $project],
            'deleteTask' => ['mutation ($id: ID!) { deleteTask(id: $id) { id } }', $task],
            'emptyProjectTrash' => ['mutation ($p: ID!) { emptyProjectTrash(projectId: $p) }', fn (self $test) => ['p' => $test->project->id]],
            'purgeFromTrash' => ['mutation ($id: ID!) { purgeFromTrash(deletionId: $id) }', fn (self $test) => ['id' => $test->deletionId]],
            'deleteTodo' => ['mutation ($id: ID!) { deleteTodo(id: $id) { id } }', $todo],
            'moveMilestone' => ['mutation ($id: ID!) { moveMilestone(id: $id, position: 0) { id } }', $milestone],
            'moveTask' => ['mutation ($id: ID!) { moveTask(id: $id, status: DONE, position: 0) { id } }', $task],
            'importPlan' => ['mutation ($p: ID!, $d: String!) { importPlan(projectId: $p, document: $d) { ok } }', fn (self $test) => ['p' => $test->project->id, 'd' => '{}']],
            'moveTaskInTodo' => ['mutation ($id: ID!) { moveTaskInTodo(id: $id, position: 0) { id } }', $task],
            'moveTaskToTodo' => ['mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { id } }', fn (self $test) => ['id' => $test->task->id, 't' => $test->todo->id]],
            'moveTodo' => ['mutation ($id: ID!, $m: ID!) { moveTodo(id: $id, milestoneId: $m, position: 0) { id } }', fn (self $test) => ['id' => $test->todo->id, 'm' => $test->milestone->id]],
            'removeProjectMember' => ['mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['member']->id]],
            'updateProjectLabels' => ['mutation ($p: ID!) { updateProjectLabels(projectId: $p, labels: []) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'applyTemplateLabels' => ['mutation ($p: ID!, $t: ID!) { applyTemplateLabels(projectId: $p, templateId: $t, mode: APPEND) { id } }', fn (self $test) => ['p' => $test->project->id, 't' => Template::factory()->create()->id]],
            'setProjectMember' => ['mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "member") { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['stranger']->id]],
            'setTaskLabels' => ['mutation ($t: ID!) { setTaskLabels(taskId: $t, labelIds: []) { id } }', fn (self $test) => ['t' => $test->task->id]],
            'updateComment' => ['mutation ($id: ID!, $b: RichText!) { updateComment(id: $id, input: {body: $b}) { id } }', fn (self $test) => ['id' => $test->comment->id, 'b' => $test->doc('Neu')]],
            'updateMilestone' => ['mutation ($id: ID!) { updateMilestone(id: $id, input: {title: "Neu"}) { id } }', $milestone],
            'updateProject' => ['mutation ($id: ID!) { updateProject(id: $id, input: {name: "Neu"}) { id } }', $project],
            'updateTask' => ['mutation ($id: ID!) { updateTask(id: $id, input: {title: "Neu"}) { id } }', $task],
            'updateTodo' => ['mutation ($id: ID!) { updateTodo(id: $id, input: {title: "Neu"}) { id } }', $todo],
        ];
    }

    public function test_every_schema_mutation_is_either_refused_when_archived_or_excluded(): void
    {
        $unclassified = array_values(array_diff($this->schemaMutations(), array_keys(self::refusedWhenArchived()), array_keys(self::EXCLUDED)));
        sort($unclassified);

        $this->assertSame([], $unclassified, 'Mutations without an archive decision: '.implode(', ', $unclassified));
    }

    public function test_the_lists_name_only_mutations_of_the_schema(): void
    {
        $unknown = array_values(array_diff([...array_keys(self::refusedWhenArchived()), ...array_keys(self::EXCLUDED)], $this->schemaMutations()));

        $this->assertSame([], $unknown, 'Listed mutations the schema does not have: '.implode(', ', $unknown));
    }

    public function test_a_mutation_is_never_on_both_lists(): void
    {
        $this->assertSame([], array_values(array_intersect(array_keys(self::refusedWhenArchived()), array_keys(self::EXCLUDED))));
    }

    public function test_each_case_sends_the_mutation_it_is_named_after(): void
    {
        foreach (self::refusedWhenArchived() as $name => [$mutation]) {
            preg_match('/mutation\b[^{]*\{\s*(\w+)/', $mutation, $match);
            $this->assertSame($name, $match[1] ?? null);
        }
    }

    /**
     * @param  \Closure(self): array<string, mixed>  $variables
     */
    private function assertRefused(string $actor, string $mutation, \Closure $variables, ?string $message = null): void
    {
        $before = $this->snapshot();

        $this->as($actor)->withHeader('Accept-Language', 'de')->gql($mutation, $variables($this))
            ->assertGraphQLErrorMessage($message ?? trans('projects.archived', [], 'de'));

        $this->assertEquals($before, $this->snapshot());
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        return collect(self::TABLES)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->map(fn (object $row) => (array) $row)->all()])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function schemaMutations(): array
    {
        return $this->app->make(SchemaBuilder::class)->schema()->getMutationType()->getFieldNames();
    }
}
