<?php

namespace Tests\Feature\Planer;

use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Tools\ExportPlanTool;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\Canonical;
use App\Planer\Export;
use App\Planer\Validator;
use App\Services\Deletion;
use RuntimeException;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * The export of a project (docs/auftrag-m4.md §3): the document, its
 * canonical text, who may ask for it, and that it changes nothing.
 */
class ExportTest extends GraphQLTestCase
{
    private const QUERY = 'query ($id: ID!) { exportPlan(projectId: $id) }';

    private function export(): array
    {
        return app(Export::class)->document($this->project->fresh());
    }

    public function test_the_document_follows_the_project(): void
    {
        $this->project->update(['description' => $this->doc('Für alle.')]);
        $backend = Label::factory()->for($this->project)->create(['key' => 'backend', 'names' => ['de' => 'Backend', 'en' => 'Backend'], 'category' => 'area', 'color' => 'cyan', 'position' => 1]);
        $bug = Label::factory()->for($this->project)->create(['key' => 'bug', 'names' => ['de' => 'Fehler', 'en' => 'Bug'], 'color' => 'red', 'position' => 0]);

        $this->milestone->update(['title' => 'Start', 'due_date' => '2026-12-01', 'release_version' => '1.0', 'release_status' => 'planned']);
        $this->todo->update(['title' => 'Aufbau', 'description' => $this->doc('Details')]);
        $this->task->update(['title' => 'Datenbank', 'due_date' => '2026-11-20', 'user_story' => ['role' => 'Admin', 'goal' => 'ein Backup', 'benefit' => 'nichts verloren geht']]);
        $this->task->labels()->attach([$backend->id, $bug->id]);
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id, 'status' => 'done', 'commit' => 'abc1234'])->save();

        $document = $this->export();

        $this->assertSame('planer-v2', $document['format']);
        $this->assertSame((int) $this->project->fresh()->planer_revision, $document['revision']);
        $this->assertSame('Mollo Planer', $document['project']['name']);
        $this->assertSame('de', $document['project']['locale']);
        $this->assertSame('Für alle.', $document['project']['description']);
        $this->assertContains(['email' => $this->users['owner']->email, 'role' => 'owner'], $document['project']['members']);
        $this->assertSame(['bug', 'backend'], array_column($document['project']['labels'], 'key'));
        $this->assertSame(['de' => 'Fehler', 'en' => 'Bug'], $document['project']['labels'][0]['names']);

        $milestone = $document['milestones'][0];
        $this->assertSame("m-{$this->milestone->id}", $milestone['id']);
        $this->assertSame('2026-12-01', $milestone['dueDate']);
        $this->assertSame(['version' => '1.0', 'status' => 'planned'], $milestone['release']);
        $this->assertSame("t-{$this->todo->id}", $milestone['todos'][0]['id']);
        $this->assertSame('Details', $milestone['todos'][0]['description']);

        $task = $milestone['todos'][0]['tasks'][0];
        $this->assertSame("k-{$this->task->id}", $task['id']);
        $this->assertSame('done', $task['status']);
        $this->assertSame($this->users['member']->email, $task['assigneeEmail']);
        $this->assertSame(['bug', 'backend'], $task['labels']);
        $this->assertSame('abc1234', $task['commit']);
        $this->assertSame(['role' => 'Admin', 'goal' => 'ein Backup', 'benefit' => 'nichts verloren geht'], $task['userStory']);
        $this->assertSame([], Validator::errors($document));
    }

    public function test_members_come_by_name(): void
    {
        $names = collect($this->export()['project']['members'])->pluck('email')->all();

        $this->assertSame(
            collect([$this->users['owner'], $this->users['manager'], $this->users['member']])->sortBy(fn ($user) => mb_strtolower($user->name))->pluck('email')->values()->all(),
            $names,
        );
    }

    public function test_a_member_with_an_own_role_has_its_key(): void
    {
        $role = Role::query()->where('scope', 'project')->where('key', 'guest')->firstOrFail();
        $this->project->members()->updateExistingPivot($this->users['member']->id, ['role_id' => $role->id]);
        Role::forgetRegistry();

        $roles = collect($this->export()['project']['members'])->pluck('role', 'email');

        $this->assertSame('guest', $roles[$this->users['member']->email]);
    }

    public function test_tasks_come_in_their_order_and_the_trash_is_left_out(): void
    {
        $second = Task::factory()->for($this->todo)->create(['title' => 'Zweiter']);
        $third = Task::factory()->for($this->todo)->create(['title' => 'Dritter']);
        $this->as('member')->gql('mutation ($id: ID!) { moveTaskInTodo(id: $id, position: 0) { id } }', ['id' => $third->id]);
        $this->actingAs($this->users['owner']);
        app(Deletion::class)->delete($second);
        $gone = Milestone::factory()->for($this->project)->create();
        app(Deletion::class)->delete($gone);

        $document = $this->export();

        $this->assertSame(["k-{$third->id}", "k-{$this->task->id}"], array_column($document['milestones'][0]['todos'][0]['tasks'], 'id'));
        $this->assertCount(1, $document['milestones']);
    }

    public function test_the_text_is_canonical_and_the_revision_does_not_change(): void
    {
        $before = (int) $this->project->fresh()->planer_revision;

        $text = app(Export::class)->text($this->project->fresh());

        $this->assertSame($text, Canonical::text(json_decode($text, true)));
        $this->assertStringEndsWith("}\n", $text);
        $this->assertSame($before, (int) $this->project->fresh()->planer_revision);
    }

    public function test_an_empty_project_exports_with_no_milestones(): void
    {
        Milestone::query()->where('project_id', $this->project->id)->get()->each(fn (Milestone $milestone) => $milestone->forceDelete());

        $this->assertSame([], $this->export()['milestones']);
    }

    public function test_a_project_that_breaks_a_limit_names_the_place(): void
    {
        Todo::withoutEvents(fn () => Todo::factory()->count(51)->for($this->milestone)->create(['project_id' => $this->project->id]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/milestones\[0\]\.todos/');

        $this->export();
    }

    public function test_graphql_returns_the_text_to_everyone_with_the_right(): void
    {
        foreach (['owner', 'manager', 'member'] as $name) {
            $this->as($name)->gql(self::QUERY, ['id' => $this->project->id])
                ->assertJsonMissingPath('errors')
                ->assertJsonPath('data.exportPlan', app(Export::class)->text($this->project->fresh()));
        }
    }

    public function test_a_stranger_may_not_export(): void
    {
        $this->assertUnauthorized($this->as('stranger')->gql(self::QUERY, ['id' => $this->project->id]));
    }

    public function test_an_archived_project_can_be_exported(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->as('owner')->gql(self::QUERY, ['id' => $this->project->id])->assertJsonMissingPath('errors');
    }

    public function test_the_mcp_tool_returns_the_document(): void
    {
        MolloPlanerServer::actingAs($this->users['member'])
            ->tool(ExportPlanTool::class, ['project_id' => (string) $this->project->id])
            ->assertOk()
            ->assertSee('"format":"planer-v2"')
            ->assertSee("\"id\":\"m-{$this->milestone->id}\"");
    }

    public function test_the_mcp_tool_refuses_a_stranger(): void
    {
        MolloPlanerServer::actingAs($this->users['stranger'])
            ->tool(ExportPlanTool::class, ['project_id' => (string) $this->project->id])
            ->assertHasErrors();
    }
}
