<?php

namespace Tests\Feature\Mcp;

use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * MCP polish (Auftrag 65): list_tasks by todo and label, a single value for a list, import_plan with `document`, a hint on
 * the unfiltered export, update_tasks with title and labels.
 */
class McpPolishTest extends GraphQLTestCase
{
    private Todo $otherTodo;

    private Task $second;

    private Label $bug;

    private Label $research;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task->forceFill(['status' => TaskStatus::REVIEW, 'title' => 'Erster Task'])->saveQuietly();
        $this->otherTodo = Todo::factory()->for(Milestone::factory()->for($this->project)->create(['position' => 1]))->create();
        $this->second = Task::factory()->for($this->otherTodo)->create(['board_position' => 1, 'status' => TaskStatus::OPEN, 'title' => 'Zweiter Task']);
        $this->bug = Label::factory()->for($this->project)->create(['key' => 'bug']);
        $this->research = Label::factory()->for($this->project)->create(['key' => 'research']);
        $this->task->labels()->attach($this->bug);
        $this->second->labels()->attach($this->research);
    }

    private function tool(string $name, array $arguments, string $as = 'owner'): mixed
    {
        return MolloPlanerServer::actingAs($this->users[$as])->tool(McpTools::of($name), ['project_id' => (string) $this->project->id, ...$arguments]);
    }

    /** @return list<string> ids of the tasks in the answer */
    private function ids(mixed $response): array
    {
        $text = (fn (): string => (string) ($this->content()[0] ?? ''))->call($response);

        return array_column(json_decode($text, true)['tasks'] ?? [], 'id');
    }

    // A

    public function test_list_tasks_filters_by_todo(): void
    {
        $this->assertSame(["k-{$this->second->id}"], $this->ids($this->tool('list_tasks', ['todo_ids' => ["t-{$this->otherTodo->id}"]])));
        $this->assertSame(["k-{$this->task->id}"], $this->ids($this->tool('list_tasks', ['todo_ids' => [(string) $this->todo->id]])));
    }

    public function test_list_tasks_filters_by_label_with_at_least_one_of_them(): void
    {
        $this->assertSame(["k-{$this->task->id}"], $this->ids($this->tool('list_tasks', ['labels' => ['bug']])));
        $this->assertEqualsCanonicalizing(["k-{$this->task->id}", "k-{$this->second->id}"], $this->ids($this->tool('list_tasks', ['labels' => ['bug', 'research']])));
        $this->assertSame([], $this->ids($this->tool('list_tasks', ['labels' => ['unbekannt']])));
    }

    public function test_the_new_filters_combine_with_the_old_ones(): void
    {
        $this->assertSame([], $this->ids($this->tool('list_tasks', ['labels' => ['bug'], 'status' => ['open']])));
        $this->assertSame(["k-{$this->task->id}"], $this->ids($this->tool('list_tasks', ['labels' => ['bug'], 'status' => ['review'], 'todo_ids' => [(string) $this->todo->id]])));
    }

    public function test_a_todo_filter_does_not_show_what_the_person_may_not_see(): void
    {
        $this->tool('list_tasks', ['todo_ids' => [(string) $this->todo->id]], 'stranger')->assertHasErrors(['not found']);
    }

    // B

    public function test_a_single_value_works_where_a_list_is_meant(): void
    {
        $this->assertSame(["k-{$this->task->id}"], $this->ids($this->tool('list_tasks', ['status' => 'review'])));
        $this->tool('export_plan', ['task_status' => 'review'])->assertOk()->assertSee('Erster Task')->assertDontSee('Zweiter Task');
    }

    public function test_a_too_long_title_says_the_limit_and_the_length(): void
    {
        $plan = ['format' => 'planer-v2', 'milestones' => [['id' => "m-{$this->milestone->id}", 'todos' => [['id' => "t-{$this->todo->id}", 'tasks' => [['id' => "k-{$this->task->id}", 'title' => str_repeat('a', 187)]]]]]]];

        $this->tool('import_plan', ['mode' => 'partial', 'dry_run' => true, 'plan' => $plan])
            ->assertHasErrors(['Höchstens 160 Zeichen, hier 187']);
    }

    public function test_import_plan_takes_the_document_of_an_export_as_it_is(): void
    {
        $export = (fn (): string => (string) ($this->content()[0] ?? ''))->call($this->tool('export_plan', []));
        $document = json_decode($export, true)['document'];
        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Neu benannt';

        $this->tool('import_plan', ['mode' => 'partial', 'document' => $document])->assertOk();

        $this->assertSame('Neu benannt', $this->task->fresh()->title);
    }

    public function test_import_plan_refuses_plan_and_document_together_and_neither(): void
    {
        $plan = ['format' => 'planer-v2', 'milestones' => []];

        $this->tool('import_plan', ['plan' => $plan, 'document' => $plan])->assertHasErrors(['not both']);
        $this->tool('import_plan', [])->assertHasErrors(['Give plan']);
    }

    // C

    public function test_an_unfiltered_export_carries_its_size_and_the_cheaper_ways(): void
    {
        $this->tool('export_plan', [])->assertOk()->assertSee('"characters"')->assertSee('outline');
    }

    public function test_a_filtered_export_has_no_hint(): void
    {
        foreach ([['view' => 'outline'], ['milestone_ids' => ["m-{$this->milestone->id}"]], ['task_status' => ['open']], ['include_descriptions' => false], ['include_released' => false]] as $filter) {
            $this->tool('export_plan', $filter)->assertOk()->assertDontSee('"characters"');
        }
    }

    // D

    public function test_update_tasks_sets_title_and_labels(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'title' => 'Besser benannt', 'labels' => ['bug', 'research']]]])
            ->assertOk()
            ->assertSee('Besser benannt');

        $this->assertSame('Besser benannt', $this->task->fresh()->title);
        $this->assertEqualsCanonicalizing(['bug', 'research'], $this->task->fresh()->labels->pluck('key')->all());
    }

    public function test_update_tasks_clears_labels_with_an_empty_list(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'labels' => []]]])->assertOk();

        $this->assertSame([], $this->task->fresh()->labels->pluck('key')->all());
    }

    public function test_update_tasks_creates_no_label_and_writes_nothing_for_an_unknown_one(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'title' => 'Nicht übernommen', 'labels' => ['gibt-es-nicht']]]])->assertHasErrors();

        $this->assertSame('Erster Task', $this->task->fresh()->title);
        $this->assertSame(2, Label::query()->where('project_id', $this->project->id)->count());
    }

    public function test_update_tasks_refuses_a_title_that_is_too_long_and_one_who_may_not_edit(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'title' => str_repeat('a', 187)]]])->assertHasErrors(['160']);
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'title' => 'Fremd']]], 'stranger')->assertHasErrors();

        $this->assertSame('Erster Task', $this->task->fresh()->title);
    }

    public function test_the_old_fields_still_work_and_unchanged_ones_are_not_reported(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->task->id}", 'status' => 'done', 'commit' => 'abc1234']]])
            ->assertOk()
            ->assertSee('"status":["review","done"]')
            ->assertDontSee('"title"')
            ->assertDontSee('"labels"');
    }
}
