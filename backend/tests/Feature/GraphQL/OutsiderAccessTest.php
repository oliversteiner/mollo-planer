<?php

namespace Tests\Feature\GraphQL;

use App\Models\Comment;
use App\Models\Document;
use App\Models\Idea;
use App\Models\Task;
use App\Models\Template;
use App\Services\Deletion;
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
    private const TABLES = ['users', 'projects', 'project_user', 'milestones', 'todos', 'tasks', 'comments', 'app_settings', 'documents', 'document_shares', 'document_versions'];

    private Comment $comment;

    private Document $document;

    /** A task in the project's trash. */
    private string $deletionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->document = Document::factory()->for($this->project)->create(['owner_id' => $this->users['owner']->id]);
        $this->comment = Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create();
        $this->deletionId = (string) app(Deletion::class)->delete(Task::factory()->for($this->todo)->create())->deletion_id;
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
        $template = fn (self $test) => ['id' => Template::factory()->create()->id];
        $anyRole = fn (self $test) => ['id' => DB::table('roles')->where(['scope' => 'project', 'key' => 'member'])->value('id')];
        $anyFunction = fn (self $test) => ['id' => DB::table('functions')->where(['scope' => 'project', 'key' => 'contribute'])->value('id')];

        return [
            'updateProject' => ['stranger', 'mutation ($id: ID!) { updateProject(id: $id, input: {name: "Hack"}) { id } }', $project],
            'deleteProject' => ['stranger', 'mutation ($id: ID!) { deleteProject(id: $id) { id } }', $project],
            'restoreFromTrash' => ['stranger', 'mutation ($id: ID!) { restoreFromTrash(deletionId: $id) { id } }', fn (self $test) => ['id' => $test->deletionId]],
            'purgeFromTrash (manager, without trash.purge)' => ['manager', 'mutation ($id: ID!) { purgeFromTrash(deletionId: $id) }', fn (self $test) => ['id' => $test->deletionId]],
            'emptyProjectTrash (manager, without trash.purge)' => ['manager', 'mutation ($p: ID!) { emptyProjectTrash(projectId: $p) }', fn (self $test) => ['p' => $test->project->id]],
            'archiveProject (member)' => ['member', 'mutation ($id: ID!) { archiveProject(id: $id) { id } }', $project],
            'unarchiveProject (member)' => ['member', 'mutation ($id: ID!) { unarchiveProject(id: $id) { id } }', $project],
            'setProjectMember (join as owner)' => ['stranger', 'mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "owner") { id } }', fn (self $test) => ['p' => $test->project->id, 'u' => $test->users['stranger']->id]],
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
            'createTemplateFromProject' => ['stranger', 'mutation ($p: ID!) { createTemplateFromProject(projectId: $p, name: "Hack") { id } }', fn (self $test) => ['p' => $test->project->id]],
            'createTemplateFromFile' => ['stranger', 'mutation { createTemplateFromFile(document: "{}") { id } }', fn (self $test) => []],
            'setProjectWebhook' => ['stranger', 'mutation ($p: ID!) { setProjectWebhook(projectId: $p, input: {kind: GITHUB, target: "a/b", secret: "x"}) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'removeProjectWebhook' => ['stranger', 'mutation ($p: ID!) { removeProjectWebhook(projectId: $p) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'testProjectWebhook' => ['stranger', 'mutation ($p: ID!) { testProjectWebhook(projectId: $p) { ok } }', fn (self $test) => ['p' => $test->project->id]],
            'createIdea' => ['lesend', 'mutation { createIdea(input: {title: "Nicht erlaubt"}) { id } }', fn (self $test) => []],
            'updateIdea' => ['stranger', 'mutation ($id: ID!) { updateIdea(id: $id, input: {title: "Hack"}) { id } }', fn (self $test) => ['id' => Idea::factory()->create(['user_id' => $test->users['member']->id])->id]],
            'setIdeaStatus' => ['stranger', 'mutation ($id: ID!) { setIdeaStatus(id: $id, status: DISCARDED) { id } }', fn (self $test) => ['id' => Idea::factory()->create(['user_id' => $test->users['member']->id])->id]],
            'deleteIdea' => ['stranger', 'mutation ($id: ID!) { deleteIdea(id: $id) { id } }', fn (self $test) => ['id' => Idea::factory()->create(['user_id' => $test->users['member']->id])->id]],
            'setProjectAiContext' => ['stranger', 'mutation ($p: ID!) { setProjectAiContext(projectId: $p, input: {text: "x"}) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'removeProjectAiContextFile' => ['stranger', 'mutation ($p: ID!) { removeProjectAiContextFile(projectId: $p) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'clearProjectAiContext' => ['stranger', 'mutation ($p: ID!) { clearProjectAiContext(projectId: $p) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'importPlan' => ['stranger', 'mutation ($p: ID!, $d: String!) { importPlan(projectId: $p, document: $d) { ok } }', fn (self $test) => ['p' => $test->project->id, 'd' => '{}']],
            'moveTaskInTodo' => ['stranger', 'mutation ($id: ID!) { moveTaskInTodo(id: $id, position: 0) { id } }', $task],
            'moveTaskToTodo' => ['stranger', 'mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { id } }', fn (self $test) => ['id' => $test->task->id, 't' => $test->todo->id]],
            'deleteTask' => ['stranger', 'mutation ($id: ID!) { deleteTask(id: $id) { id } }', $task],
            'addComment' => ['stranger', 'mutation ($t: ID!, $b: RichText!) { addComment(input: {taskId: $t, body: $b}) { id } }', fn (self $test) => ['t' => $test->task->id, 'b' => $test->doc('Hack')]],
            'updateComment' => ['stranger', 'mutation ($id: ID!, $b: RichText!) { updateComment(id: $id, input: {body: $b}) { id } }', fn (self $test) => ['id' => $test->comment->id, 'b' => $test->doc('Hack')]],
            'deleteComment' => ['stranger', 'mutation ($id: ID!) { deleteComment(id: $id) { id } }', $comment],
            'createProject' => ['owner', 'mutation { createProject(input: {name: "Eigenes"}) { id } }', fn (self $test) => []],
            'createUser' => ['owner', 'mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { id } }', fn (self $test) => []],
            'updateUser (make self admin)' => ['owner', 'mutation ($id: ID!) { updateUser(id: $id, input: {role: "admin"}) { id } }', fn (self $test) => ['id' => $test->users['owner']->id]],
            'resetUserTwoFactor' => ['owner', 'mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }', $member],
            'deleteUser' => ['owner', 'mutation ($id: ID!) { deleteUser(id: $id) { id } }', $member],
            'updateAppSettings (2FA off)' => ['owner', 'mutation { updateAppSettings(twoFactorEnabled: false) { twoFactorEnabled } }', fn (self $test) => []],
            'deleteUserAvatar (someone else)' => ['owner', 'mutation ($id: ID!) { deleteUserAvatar(id: $id) { id } }', $member],
            'approveUser (owner is not an admin)' => ['owner', 'mutation ($id: ID!) { approveUser(id: $id) { id } }', fn (self $test) => ['id' => $test->users['stranger']->id]],
            'rejectUser (owner is not an admin)' => ['owner', 'mutation ($id: ID!) { rejectUser(id: $id) { id } }', fn (self $test) => ['id' => $test->users['stranger']->id]],
            'moveTodo' => ['stranger', 'mutation ($id: ID!, $m: ID!) { moveTodo(id: $id, milestoneId: $m, position: 0) { id } }', fn (self $test) => ['id' => $test->todo->id, 'm' => $test->milestone->id]],
            'moveMilestone' => ['stranger', 'mutation ($id: ID!) { moveMilestone(id: $id, position: 0) { id } }', $milestone],
            'updateProjectLabels (member)' => ['member', 'mutation ($p: ID!) { updateProjectLabels(projectId: $p, labels: []) { id } }', fn (self $test) => ['p' => $test->project->id]],
            'applyTemplateLabels (member)' => ['member', 'mutation ($p: ID!, $t: ID!) { applyTemplateLabels(projectId: $p, templateId: $t, mode: APPEND) { id } }', fn (self $test) => ['p' => $test->project->id, 't' => Template::factory()->create()->id]],
            'setTaskLabels (member)' => ['member', 'mutation ($t: ID!) { setTaskLabels(taskId: $t, labelIds: []) { id } }', fn (self $test) => ['t' => $test->task->id]],
            'createRole' => ['owner', 'mutation { createRole(input: {scope: PROJECT, names: {de: "Hack", en: "Hack"}, functionIds: []}) { id } }', fn (self $test) => []],
            'updateRole' => ['owner', 'mutation ($id: ID!) { updateRole(id: $id, input: {names: {de: "Hack", en: "Hack"}}) { id } }', $anyRole],
            'duplicateRole' => ['owner', 'mutation ($id: ID!) { duplicateRole(id: $id) { id } }', $anyRole],
            'deleteRole' => ['owner', 'mutation ($id: ID!) { deleteRole(id: $id) { id } }', $anyRole],
            'setDefaultRole' => ['owner', 'mutation ($id: ID!) { setDefaultRole(id: $id) { id } }', $anyRole],
            'resetRole' => ['owner', 'mutation ($id: ID!) { resetRole(id: $id) { id } }', $anyRole],
            'createRoleFunction' => ['owner', 'mutation { createRoleFunction(input: {scope: PROJECT, names: {de: "Hack", en: "Hack"}, rights: [PROJECT_DELETE]}) { id } }', fn (self $test) => []],
            'updateRoleFunction (owner gives members everything)' => ['owner', 'mutation ($id: ID!) { updateRoleFunction(id: $id, input: {rights: [PROJECT_DELETE]}) { id } }', $anyFunction],
            'duplicateRoleFunction' => ['owner', 'mutation ($id: ID!) { duplicateRoleFunction(id: $id) { id } }', $anyFunction],
            'deleteRoleFunction' => ['owner', 'mutation ($id: ID!) { deleteRoleFunction(id: $id) { id } }', $anyFunction],
            'resetRoleFunction' => ['owner', 'mutation ($id: ID!) { resetRoleFunction(id: $id) { id } }', $anyFunction],
            'createTemplate (member)' => ['member', 'mutation { createTemplate(input: {name: "Bau", locale: DE, labels: [{names: [{locale: DE, name: "Eins"}], color: RED}]}) { id } }', fn (self $test) => []],
            'updateTemplate (member)' => ['member', 'mutation ($id: ID!) { updateTemplate(id: $id, input: {name: "Hack", labels: [{names: [{locale: DE, name: "Eins"}], color: RED}]}) { id } }', $template],
            'deleteTemplate (member)' => ['member', 'mutation ($id: ID!) { deleteTemplate(id: $id) }', $template],
            'createDocument' => ['stranger', 'mutation ($p: ID!, $c: RichText!) { createDocument(input: {projectId: $p, title: "Hack", contentJson: $c}) { id } }', fn (self $test) => ['p' => $test->project->id, 'c' => $test->doc('x')]],
            'updateDocument' => ['stranger', 'mutation ($id: ID!) { updateDocument(id: $id, revision: 1, input: {title: "Hack"}) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'updateDocument (member, document for reading)' => ['member', 'mutation ($id: ID!) { updateDocument(id: $id, revision: 1, input: {title: "Hack"}) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'setDocumentSharing' => ['stranger', 'mutation ($id: ID!) { setDocumentSharing(id: $id, input: {projectAccess: EDIT}) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'setDocumentSharing (member)' => ['member', 'mutation ($id: ID!) { setDocumentSharing(id: $id, input: {projectAccess: EDIT}) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'restoreDocumentVersion' => ['stranger', 'mutation ($id: ID!) { restoreDocumentVersion(id: $id, revision: 1) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'restoreDocumentVersion (member, document for reading)' => ['member', 'mutation ($id: ID!) { restoreDocumentVersion(id: $id, revision: 1) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'transferDocument' => ['stranger', 'mutation ($id: ID!, $u: ID!) { transferDocument(id: $id, ownerId: $u) { id } }', fn (self $test) => ['id' => $test->document->id, 'u' => $test->users['stranger']->id]],
            'transferDocument (member)' => ['member', 'mutation ($id: ID!, $u: ID!) { transferDocument(id: $id, ownerId: $u) { id } }', fn (self $test) => ['id' => $test->document->id, 'u' => $test->users['member']->id]],
            'deleteDocument' => ['stranger', 'mutation ($id: ID!) { deleteDocument(id: $id) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'deleteDocument (member)' => ['member', 'mutation ($id: ID!) { deleteDocument(id: $id) { id } }', fn (self $test) => ['id' => $test->document->id]],
            'duplicateTemplate (member)' => ['member', 'mutation ($id: ID!) { duplicateTemplate(id: $id) { id } }', $template],
        ];
    }

    #[DataProvider('hiddenQueries')]
    public function test_query_shows_nothing_of_a_foreign_project(string $query, string $path, mixed $expected): void
    {
        $variables = [
            'document' => $this->document->id,
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
            'document' => ['query ($document: ID!) { document(id: $document) { id } }', 'data.document', null],
            'documentVersion' => ['query ($document: ID!) { documentVersion(documentId: $document, revision: 1) { revision } }', 'data.documentVersion', null],
            'documents' => ['query ($project: ID!) { documents(projectId: $project) { id } }', 'data.documents', []],
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
