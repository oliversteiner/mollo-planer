<?php

namespace Tests\Feature\Planer;

use App\Enums\ProjectRole;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\ImportPlanTool;
use App\Models\Idea;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Models\Todo;
use App\Models\User;
use App\Planer\Export;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * appendOnly, templateId and ideaId of importPlan and import_plan (docs/auftrag-m6.md §1, §2.3, §4.4).
 */
class ImportExtensionsTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($p: ID, $d: String!, $dry: Boolean, $append: Boolean, $template: ID, $idea: ID) {
        importPlan(projectId: $p, document: $d, mode: PARTIAL, dryRun: $dry, appendOnly: $append, templateId: $template, ideaId: $idea) {
            ok projectId changes { kind path id } errors { path kind message }
        }
    }';

    /** @return array<string, mixed> */
    private function exported(): array
    {
        return app(Export::class)->document($this->project->fresh());
    }

    private function send(array $document, array $variables = [], string $as = 'owner')
    {
        return $this->as($as)->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document), 'append' => true, ...$variables]);
    }

    // ─── appendOnly ──────────────────────────────────────────────────

    public function test_a_new_task_between_two_anchors_is_fine_and_moves_nothing(): void
    {
        $second = Task::factory()->for($this->todo)->create(['title' => 'Zweiter']);
        $document = $this->exported();
        $tasks = $document['milestones'][0]['todos'][0]['tasks'];
        $document['milestones'][0]['todos'][0]['tasks'] = [$tasks[0], ['id' => 'k-new-1', 'title' => 'Dazwischen', 'status' => 'planned'], $tasks[1]];

        $this->send($document, ['dry' => true])->assertJsonPath('data.importPlan.ok', true)->assertJsonPath('data.importPlan.changes.0.kind', 'CREATE')->assertJsonCount(1, 'data.importPlan.changes');
        $this->assertNotNull($second);
    }

    public function test_update_move_and_delete_of_existing_things_are_errors_even_in_a_dry_run(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'Umbenannt';

        $this->send($document, ['dry' => true])
            ->assertJsonPath('data.importPlan.ok', false)
            ->assertJsonPath('data.importPlan.errors.0.kind', 'append_only')
            ->assertJsonPath('data.importPlan.errors.0.path', 'milestones[0]');

        $other = Todo::factory()->for(Milestone::factory()->for($this->project))->create();
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'] = [];
        $document['milestones'][1] = ['id' => "m-{$other->milestone_id}", 'title' => $other->milestone->title, 'todos' => [['id' => "t-{$other->id}", 'title' => $other->title, 'tasks' => [['id' => "k-{$this->task->id}", 'title' => $this->task->title, 'status' => 'open']]]]];
        $result = $this->send($document, ['dry' => true])->json('data.importPlan');

        $this->assertFalse($result['ok']);
        $this->assertContains('append_only', array_column($result['errors'], 'kind'));
        $this->assertSame($this->todo->id, $this->task->fresh()->todo_id);
    }

    public function test_the_same_file_without_append_only_may_change_things(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'Umbenannt';

        $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document), 'append' => false, 'dry' => true])->assertJsonPath('data.importPlan.ok', true);
    }

    public function test_it_holds_through_mcp_too(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'Umbenannt';

        MolloPlanerServer::actingAs($this->users['owner'])->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document, 'append_only' => true])
            ->assertHasErrors()
            ->assertSee('append_only');
        $this->assertNotSame('Umbenannt', $this->milestone->fresh()->title);
    }

    // ─── Rights of appendOnly (F9.1) ─────────────────────────────────

    private function withNewTask(): array
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Von David', 'status' => 'planned'];

        return $document;
    }

    public function test_a_member_adds_a_task_without_the_file_right(): void
    {
        $this->send($this->withNewTask(), [], 'member')->assertJsonPath('data.importPlan.ok', true);

        $this->assertSame(1, Task::query()->where('title', 'Von David')->count());
    }

    public function test_a_guest_gets_an_error_per_change_in_the_dry_run_and_nothing_is_written(): void
    {
        $guest = User::factory()->create();
        $this->project->members()->attach($guest->id, ['role_id' => $this->projectRole(ProjectRole::GUEST)->id]);
        $this->users['guest'] = $guest;

        $result = $this->send($this->withNewTodo(), ['dry' => true], 'guest')->json('data.importPlan');

        $this->assertFalse($result['ok']);
        $this->assertSame('denied', $result['errors'][0]['kind']);
        $this->assertStringContainsString('todos', $result['errors'][0]['path']);

        $this->send($this->withNewTodo(), [], 'guest')->assertJsonPath('data.importPlan.ok', false);
        $this->assertSame(0, Todo::query()->where('title', 'Aus der Idee')->count());
    }

    public function test_without_append_only_the_file_right_is_still_needed(): void
    {
        $this->send($this->withNewTask(), ['append' => false], 'member')->assertJsonPath('data', null);
    }

    public function test_somebody_who_does_not_see_the_project_is_refused_even_when_appending(): void
    {
        $this->send($this->withNewTask(), [], 'stranger')->assertJsonMissingPath('data.importPlan.ok');
        $this->assertSame(0, Task::query()->where('title', 'Von David')->count());
    }

    public function test_append_only_means_nothing_for_a_new_project_with_labels_from_a_template(): void
    {
        $template = Template::factory()->create();
        $document = $this->exported();
        $document['project']['name'] = 'Aus Vorlage mit Labels';
        $document['project']['members'] = [];
        $document['project']['labels'] = [['key' => 'presse', 'names' => ['de' => 'Presse'], 'color' => 'blue']];

        $this->as('admin')->gql(self::MUTATION, ['d' => json_encode($document), 'template' => $template->id, 'append' => true])->assertJsonPath('data.importPlan.ok', true);

        $this->assertSame($template->id, Project::query()->where('name', 'Aus Vorlage mit Labels')->sole()->template_id);
    }

    // ─── templateId ──────────────────────────────────────────────────

    public function test_a_template_belongs_to_a_new_project_only_and_sets_its_origin(): void
    {
        $template = Template::factory()->create();
        $document = $this->exported();
        $document['project']['name'] = 'Neu aus Vorlage';
        $document['project']['members'] = [];

        $this->as('admin')->gql(self::MUTATION, ['d' => json_encode($document), 'template' => $template->id])->assertJsonPath('data.importPlan.ok', true);

        $project = Project::query()->where('name', 'Neu aus Vorlage')->sole();
        $this->assertSame($template->id, $project->template_id);
        $this->as('admin')->gql('query { templates { id projectCount } }')->assertJsonPath('data.templates.0.projectCount', 1);

        $this->send($this->exported(), ['template' => $template->id])->assertGraphQLValidationKeys(['templateId']);
        $this->as('admin')->gql(self::MUTATION, ['d' => json_encode($document), 'template' => 999999])->assertGraphQLValidationKeys(['templateId']);
    }

    // ─── ideaId ──────────────────────────────────────────────────────

    private function idea(string $as = 'manager'): Idea
    {
        return Idea::factory()->create(['user_id' => $this->users[$as]->id]);
    }

    private function withNewTodo(): array
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][] = ['id' => 't-new-1', 'title' => 'Aus der Idee', 'tasks' => [['id' => 'k-new-1', 'title' => 'Aufgabe', 'status' => 'planned']]];

        return $document;
    }

    public function test_putting_an_idea_into_a_project_adopts_it_and_marks_its_new_todos(): void
    {
        $idea = $this->idea();

        $this->send($this->withNewTodo(), ['idea' => $idea->id], 'manager')->assertJsonPath('data.importPlan.ok', true);

        $idea->refresh();
        $this->assertSame('adopted', $idea->status->value);
        $this->assertNotNull($idea->adopted_at);
        $this->assertSame($this->project->id, $idea->project_id);
        $this->assertSame(['Aus der Idee'], Todo::query()->where('idea_id', $idea->id)->pluck('title')->all());
        $this->assertNull($this->todo->fresh()->idea_id);
        $this->as('manager')->gql('query ($id: ID!) { idea(id: $id) { status todos { title } } }', ['id' => $idea->id])->assertJsonPath('data.idea.todos.0.title', 'Aus der Idee');
    }

    public function test_the_project_of_the_idea_stays_when_it_had_one(): void
    {
        $other = Project::factory()->create();
        $idea = Idea::factory()->create(['user_id' => $this->users['manager']->id, 'project_id' => $other->id]);

        $this->send($this->withNewTodo(), ['idea' => $idea->id], 'manager')->assertJsonPath('data.importPlan.ok', true);

        $this->assertSame($other->id, $idea->fresh()->project_id);
    }

    public function test_a_dry_run_changes_nothing_about_the_idea(): void
    {
        $idea = $this->idea();

        $this->send($this->withNewTodo(), ['idea' => $idea->id, 'dry' => true], 'manager')->assertJsonPath('data.importPlan.ok', true);

        $this->assertSame('open', $idea->fresh()->status->value);
        $this->assertNull($idea->fresh()->project_id);
        $this->assertSame(0, Todo::query()->whereNotNull('idea_id')->count());
    }

    public function test_only_the_author_puts_an_idea_into_a_plan(): void
    {
        $idea = $this->idea('member');

        $this->send($this->withNewTodo(), ['idea' => $idea->id], 'manager')->assertGraphQLValidationKeys(['ideaId']);

        // With ideas.viewAny an admin reads it, but may not put it in.
        $result = $this->send($this->withNewTodo(), ['idea' => $idea->id], 'admin')->json('data.importPlan');
        $this->assertFalse($result['ok']);
        $this->assertSame('denied', $result['errors'][0]['kind']);
        $this->assertSame('open', $idea->fresh()->status->value);
        $this->assertSame(0, Todo::query()->where('title', 'Aus der Idee')->count());
    }

    public function test_an_archived_project_refuses_the_idea_like_any_import(): void
    {
        $idea = $this->idea();
        $this->project->forceFill(['archived_at' => now()])->save();

        $result = $this->send($this->withNewTodo(), ['idea' => $idea->id], 'manager')->json('data.importPlan');

        $this->assertFalse($result['ok']);
        $this->assertSame('archived', $result['errors'][0]['kind']);
        $this->assertSame('open', $idea->fresh()->status->value);
    }

    public function test_an_error_in_the_file_leaves_the_idea_open(): void
    {
        $idea = $this->idea();
        $document = $this->withNewTodo();
        $document['milestones'][0]['todos'][1]['tasks'][0]['labels'] = ['gibt-es-nicht'];

        $this->send($document, ['idea' => $idea->id], 'manager')->assertJsonPath('data.importPlan.ok', false);

        $this->assertSame('open', $idea->fresh()->status->value);
    }
}
