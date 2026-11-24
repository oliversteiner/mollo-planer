<?php

namespace Tests\Feature\Planer;

use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\Export;
use App\Planer\Import;
use App\Planer\ImportMode;
use App\Planer\ImportResult;
use App\Planer\Validator;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * An excerpt without ballast (Auftrag 49 A, spec/format-v2.md «Ausschnitt»): in mode partial an existing element needs
 * only its id and the fields that change; what is missing stays as it is. `revision` and `project` are optional,
 * labels and assignees are resolved against the project. New elements need what they always need. Mode full is
 * exactly as before.
 */
class ImportExcerptTest extends GraphQLTestCase
{
    private Todo $second;

    private Task $other;

    private Task $third;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task->forceFill(['title' => 'Erste Aufgabe', 'status' => TaskStatus::OPEN, 'assigned_user_id' => $this->users['member']->id])->save();
        $this->other = Task::factory()->for($this->todo)->create(['title' => 'Zweite Aufgabe', 'status' => TaskStatus::OPEN, 'board_position' => 1]);
        $this->second = Todo::factory()->for($this->milestone)->create(['title' => 'Anderes ToDo']);
        $this->third = Task::factory()->for($this->second)->create(['title' => 'Dritte Aufgabe', 'status' => TaskStatus::PLANNED, 'board_position' => 2]);
        Label::factory()->for($this->project)->create(['key' => 'chore', 'names' => ['de' => 'Pflege', 'en' => 'Chore']]);
        Label::factory()->for($this->project)->create(['key' => 'bug', 'names' => ['de' => 'Fehler', 'en' => 'Bug']]);
    }

    /** @param array<string, mixed> $document */
    private function import(array $document, ImportMode $mode = ImportMode::Partial, bool $dryRun = false, ?Project $project = null): ImportResult
    {
        return app(Import::class)->run($project ?? $this->project, $document, $mode, $dryRun, false, $this->users['owner']);
    }

    /**
     * @param  list<array<string, mixed>>  $todos
     * @return array<string, mixed>
     */
    private function excerpt(array $todos): array
    {
        return ['format' => 'planer-v2', 'milestones' => [['id' => "m-{$this->milestone->id}", 'todos' => $todos]]];
    }

    public function test_three_tasks_in_two_todos_change_their_status_with_ids_and_status_only(): void
    {
        $before = app(Export::class)->document($this->project->fresh());

        $result = $this->import($this->excerpt([
            ['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'in_progress'], ['id' => "k-{$this->other->id}", 'status' => 'review']]],
            ['id' => "t-{$this->second->id}", 'tasks' => [['id' => "k-{$this->third->id}", 'status' => 'open']]],
        ]));

        $this->assertSame([], $result->errors);
        $this->assertSame(['update', 'update', 'update'], array_column($result->changes, 'kind'));
        $this->assertSame([['status'], ['status'], ['status']], array_column($result->changes, 'fields'));
        $this->assertSame(TaskStatus::IN_PROGRESS, $this->task->fresh()->status);
        $this->assertSame(TaskStatus::REVIEW, $this->other->fresh()->status);
        $this->assertSame(TaskStatus::OPEN, $this->third->fresh()->status);

        // Everything else is as it was: titles, assignee, order, the other fields of the plan.
        $after = app(Export::class)->document($this->project->fresh());
        $strip = function (array $plan): array {
            unset($plan['revision']);

            foreach ($plan['milestones'] as &$milestone) {
                foreach ($milestone['todos'] as &$todo) {
                    foreach ($todo['tasks'] as &$task) {
                        unset($task['status']);
                    }
                }
            }

            return $plan;
        };
        $this->assertSame($strip($before), $strip($after));
    }

    public function test_the_same_through_the_mcp_tool_without_revision_and_project(): void
    {
        MolloPlanerServer::actingAs($this->users['owner'])->tool(McpTools::of('import_plan'), [
            'project_id' => (string) $this->project->id,
            'mode' => 'partial',
            'plan' => $this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'done', 'commit' => 'abc1234']]]]),
        ])->assertOk()->assertSee('"ok":true', false);

        $this->assertSame(TaskStatus::DONE, $this->task->fresh()->status);
        $this->assertSame('abc1234', $this->task->fresh()->commit);
    }

    public function test_a_missing_title_or_field_leaves_it_unchanged(): void
    {
        $this->milestone->forceFill(['title' => 'Etappe'])->save();

        $result = $this->import(['format' => 'planer-v2', 'milestones' => [[
            'id' => "m-{$this->milestone->id}",
            'dueDate' => '2026-12-31',
            'todos' => [['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'title' => 'Neuer Titel']]]],
        ]]]);

        $this->assertSame([], $result->errors);
        $this->assertSame('Etappe', $this->milestone->fresh()->title);
        $this->assertSame('2026-12-31', $this->milestone->fresh()->due_date->format('Y-m-d'));
        $this->assertSame('Anderes ToDo', $this->second->fresh()->title);
        $task = $this->task->fresh();
        $this->assertSame('Neuer Titel', $task->title);
        $this->assertSame(TaskStatus::OPEN, $task->status, 'no status in the excerpt: unchanged');
        $this->assertSame($this->users['member']->id, $task->assigned_user_id, 'no assignee in the excerpt: unchanged');
    }

    public function test_labels_and_the_assignee_are_resolved_against_the_project(): void
    {
        $result = $this->import($this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->other->id}", 'labels' => ['chore'], 'assigneeEmail' => $this->users['manager']->email]]]]));

        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->warnings);
        $this->assertSame(['chore'], $this->other->fresh()->labels()->pluck('key')->all());
        $this->assertSame($this->users['manager']->id, $this->other->fresh()->assigned_user_id);
    }

    public function test_a_label_the_project_does_not_have_names_the_ones_it_has(): void
    {
        app()->setLocale('de');
        $result = $this->import($this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->other->id}", 'labels' => ['feature']]]]]));

        $this->assertSame('milestones[0].todos[0].tasks[0].labels[0]', $result->errors[0]['path']);
        $this->assertSame('«feature» ist kein Label des Projekts. Vorhanden: chore, bug.', $result->errors[0]['message']);
        $this->assertSame([], $this->other->fresh()->labels()->pluck('key')->all());
    }

    public function test_project_labels_in_the_excerpt_are_still_a_wanted_change(): void
    {
        $document = $this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->other->id}", 'labels' => ['feature']]]]]);
        $document['project'] = ['labels' => [['key' => 'feature', 'names' => ['de' => 'Funktion', 'en' => 'Feature'], 'color' => 'green']]];

        $this->assertSame([], $this->import($document)->errors);
        // As before in partial: the labels of the document come first, the others keep their order after them.
        $this->assertSame(['feature', 'chore', 'bug'], $this->project->labels()->orderBy('position')->pluck('key')->all());
        $this->assertSame(['feature'], $this->other->fresh()->labels()->pluck('key')->all());
    }

    public function test_an_outdated_revision_is_not_checked_in_partial_but_base_revision_is(): void
    {
        $document = $this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]]]);
        $document['revision'] = (int) $this->project->fresh()->planer_revision + 7;

        // Decision Oliver (Auftrag 49 A): partial does not check the revision of the document, as before.
        $this->assertSame([], $this->import($document, dryRun: true)->errors);

        MolloPlanerServer::actingAs($this->users['owner'])->tool(McpTools::of('import_plan'), [
            'project_id' => (string) $this->project->id,
            'mode' => 'partial',
            'base_revision' => (int) $this->project->fresh()->planer_revision + 1,
            'plan' => $document,
        ])->assertHasErrors(['"path":"revision"']);

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
    }

    public function test_a_new_element_without_a_title_is_an_error(): void
    {
        app()->setLocale('de');
        $result = $this->import($this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => 'k-new-1', 'status' => 'open']]], ['id' => 't-new-1']]));

        $paths = array_column($result->errors, 'path');
        $this->assertContains('milestones[0].todos[0].tasks[0].title', $paths);
        $this->assertContains('milestones[0].todos[1].title', $paths);
        $this->assertSame(3, Task::query()->count());
    }

    public function test_full_mode_is_exactly_as_before(): void
    {
        $excerpt = $this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'done']]]]);

        $paths = array_column($this->import($excerpt, ImportMode::Full, dryRun: true)->errors, 'path');
        $this->assertContains('revision', $paths);
        $this->assertContains('project', $paths);

        $whole = app(Export::class)->document($this->project->fresh());
        unset($whole['milestones'][0]['todos'][0]['tasks'][0]['title']);
        $this->assertContains('milestones[0].todos[0].tasks[0].title', array_column($this->import($whole, ImportMode::Full, dryRun: true)->errors, 'path'));
        $this->assertNotSame([], Validator::errors(['format' => 'planer-v2', 'milestones' => []]), 'the full schema still needs revision and project');
    }

    public function test_an_old_complete_excerpt_gives_the_same_result_as_before(): void
    {
        $document = app(Export::class)->document($this->project->fresh());
        $document['excerpt'] = true;
        $document['milestones'][0]['todos'][0]['tasks'][1]['status'] = 'done';
        $document['milestones'][0]['todos'][0]['tasks'][1]['commit'] = 'none';

        $result = $this->import($document);

        $this->assertSame([], $result->errors);
        $this->assertSame([['kind' => 'update', 'path' => 'milestones[0].todos[0].tasks[1]', 'id' => "k-{$this->other->id}", 'fields' => ['commit', 'status'], 'title' => 'Zweite Aufgabe']], $result->changes);
    }

    public function test_one_element_changes_no_places_and_siblings_in_another_order_move(): void
    {
        $alone = $this->import($this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->other->id}", 'status' => 'review']]]]));

        $this->assertNotContains('move', array_column($alone->changes, 'kind'));
        $this->assertSame([$this->task->id, $this->other->id], Task::query()->where('todo_id', $this->todo->id)->orderBy('position')->pluck('id')->all());

        $swapped = $this->import($this->excerpt([['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->other->id}"], ['id' => "k-{$this->task->id}"]]]]));

        $this->assertContains('move', array_column($swapped->changes, 'kind'));
        $this->assertSame([$this->other->id, $this->task->id], Task::query()->where('todo_id', $this->todo->id)->orderBy('position')->pluck('id')->all());
    }

    public function test_a_task_under_another_todo_moves_there(): void
    {
        $result = $this->import($this->excerpt([['id' => "t-{$this->second->id}", 'tasks' => [['id' => "k-{$this->other->id}"]]]]));

        $this->assertSame([], $result->errors);
        $this->assertSame(['move'], array_column($result->changes, 'kind'));
        $this->assertSame($this->second->id, $this->other->fresh()->todo_id);
        $this->assertSame('Zweite Aufgabe', $this->other->fresh()->title);
    }
}
