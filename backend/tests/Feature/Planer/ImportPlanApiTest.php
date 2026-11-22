<?php

namespace Tests\Feature\Planer;

use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\ImportPlanTool;
use App\Models\Project;
use App\Models\Task;
use App\Planer\Export;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * importPlan in GraphQL and import_plan in MCP (docs/auftrag-m4.md §4.3).
 */
class ImportPlanApiTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($p: ID, $d: String!, $mode: ImportMode, $dry: Boolean) {
        importPlan(projectId: $p, document: $d, mode: $mode, dryRun: $dry) {
            ok dryRun projectId document changes { kind path id fields } errors { path kind message } idMap { from to }
        }
    }';

    /** @return array<string, mixed> */
    private function exported(): array
    {
        return app(Export::class)->document($this->project->fresh());
    }

    public function test_a_dry_run_reports_the_changes_and_writes_nothing(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open'];

        $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document), 'dry' => true])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', true)
            ->assertJsonPath('data.importPlan.dryRun', true)
            ->assertJsonPath('data.importPlan.changes.0.kind', 'CREATE')
            ->assertJsonPath('data.importPlan.changes.0.path', 'milestones[0].todos[0].tasks[1]')
            ->assertJsonPath('data.importPlan.document', null);

        $this->assertNull(Task::query()->where('title', 'Neu')->first());
    }

    public function test_the_import_writes_and_returns_the_ids_and_the_new_text(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Neu', 'status' => 'open'];
        $revision = (int) $this->project->fresh()->planer_revision;

        $response = $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document)])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', true)
            ->assertJsonPath('data.importPlan.idMap.0.from', 'k-new-1');

        $task = Task::query()->where('title', 'Neu')->sole();
        $this->assertSame("k-{$task->id}", $response->json('data.importPlan.idMap.0.to'));
        $this->assertSame($revision + 1, (int) $this->project->fresh()->planer_revision);
        $this->assertSame(app(Export::class)->text($this->project->fresh()), $response->json('data.importPlan.document'));
    }

    public function test_errors_come_with_their_path_and_nothing_is_written(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'X';

        $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($document)])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', false)
            ->assertJsonPath('data.importPlan.errors.0.path', 'milestones[0].title')
            ->assertJsonPath('data.importPlan.errors.0.kind', 'invalid');
    }

    public function test_a_text_that_is_not_json_is_an_error_in_the_result(): void
    {
        $this->as('owner')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => '{kaputt'])
            ->assertJsonPath('data.importPlan.ok', false)
            ->assertJsonPath('data.importPlan.errors.0.kind', 'invalid');
    }

    public function test_an_assignee_who_is_not_a_member_comes_back_as_a_warning(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Mit fremder Person', 'status' => 'open', 'assigneeEmail' => $this->users['stranger']->email];

        $this->as('owner')->gql('mutation ($p: ID, $d: String!, $dry: Boolean) { importPlan(projectId: $p, document: $d, dryRun: $dry) { ok warnings { path email message } } }', ['p' => $this->project->id, 'd' => json_encode($document), 'dry' => true])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', true)
            ->assertJsonPath('data.importPlan.warnings.0.path', 'milestones[0].todos[0].tasks[1]')
            ->assertJsonPath('data.importPlan.warnings.0.email', $this->users['stranger']->email);
    }

    public function test_without_a_project_it_creates_one_for_those_who_may(): void
    {
        $document = $this->exported();
        $document['project']['name'] = 'Aus Datei';
        $document['project']['members'] = [];

        $id = $this->as('admin')->gql(self::MUTATION, ['d' => json_encode($document)])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.importPlan.ok', true)
            ->json('data.importPlan.projectId');

        $project = Project::query()->findOrFail($id);
        $this->assertSame('Aus Datei', $project->name);
        $this->assertNotNull($project->roleOf($this->users['admin']));
        $this->assertSame(1, (int) $project->planer_revision);

        $this->assertUnauthorized($this->as('member')->gql(self::MUTATION, ['d' => json_encode($document)]));
    }

    public function test_the_right_to_import_is_needed(): void
    {
        $this->assertUnauthorized($this->as('member')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($this->exported()), 'dry' => true]));
        $this->as('manager')->gql(self::MUTATION, ['p' => $this->project->id, 'd' => json_encode($this->exported()), 'dry' => true])->assertJsonPath('data.importPlan.ok', true);
    }

    public function test_the_mcp_tool_dry_runs_and_imports(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Per MCP', 'status' => 'open'];

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document, 'dry_run' => true])
            ->assertOk()
            ->assertSee('"kind":"CREATE"');
        $this->assertNull(Task::query()->where('title', 'Per MCP')->first());

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document])
            ->assertOk()
            ->assertSee('"k-new-1"');
        $this->assertNotNull(Task::query()->where('title', 'Per MCP')->first());
    }

    public function test_the_mcp_tool_returns_the_errors_as_an_error(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['title'] = 'X';

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document])
            ->assertHasErrors()
            ->assertSee('milestones[0].title');
    }

    public function test_the_mcp_tool_returns_the_warnings(): void
    {
        $document = $this->exported();
        $document['milestones'][0]['todos'][0]['tasks'][] = ['id' => 'k-new-1', 'title' => 'Per MCP fremd', 'status' => 'open', 'assigneeEmail' => $this->users['stranger']->email];

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $document, 'dry_run' => true])
            ->assertOk()
            ->assertSee('"warnings"')
            ->assertSee($this->users['stranger']->email);
    }

    public function test_the_mcp_tool_refuses_a_stranger(): void
    {
        MolloPlanerServer::actingAs($this->users['stranger'])
            ->tool(ImportPlanTool::class, ['project_id' => (string) $this->project->id, 'plan' => $this->exported()])
            ->assertHasErrors();
    }
}
