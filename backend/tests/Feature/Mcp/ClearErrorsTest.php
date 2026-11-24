<?php

namespace Tests\Feature\Mcp;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Comment;
use App\Models\Idea;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Tests\McpTools;

/**
 * Clear errors instead of silent results (Auftrag 49 B, the cases of the Club DB report): a parameter a tool does not
 * have is refused with a suggestion; an ID of the wrong kind says what it is; every filter a tool offers makes the
 * answer smaller.
 */
class ClearErrorsTest extends McpTestCase
{
    private Milestone $first;

    private Milestone $second;

    private Todo $todoA;

    private Todo $todoB;

    private Task $open;

    private Task $done;

    private Task $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = Milestone::factory()->for($this->project)->create(['title' => 'Erster', 'position' => 0]);
        $this->second = Milestone::factory()->for($this->project)->create(['title' => 'Zweiter', 'position' => 1]);
        $this->todoA = Todo::factory()->for($this->first)->create(['title' => 'Todo A']);
        $this->todoB = Todo::factory()->for($this->second)->create(['title' => 'Todo B']);
        $this->open = Task::factory()->for($this->todoA)->create(['title' => 'Lampe kaufen', 'status' => TaskStatus::OPEN, 'assigned_user_id' => $this->owner->id, 'board_position' => 0]);
        $this->done = Task::factory()->for($this->todoA)->create(['title' => 'Tisch bauen', 'status' => TaskStatus::DONE, 'commit' => 'abc1234', 'board_position' => 1]);
        $this->other = Task::factory()->for($this->todoB)->create(['title' => 'Regal streichen', 'status' => TaskStatus::PLANNED, 'board_position' => 2]);
    }

    /** @param array<string, mixed> $arguments */
    private function tool(string $tool, array $arguments = []): mixed
    {
        // Tools that work across projects (ideas, users, comments of a task) are asked without a project unless the test names one.
        $withProject = in_array($tool, ['export_plan', 'list_tasks', 'update_tasks', 'list_comments'], true);

        return MolloPlanerServer::actingAs($this->owner)->tool(McpTools::of($tool), [...($withProject ? ['project_id' => (string) $this->project->id] : []), ...$arguments]);
    }

    /** @param array<string, mixed> $arguments */
    private function data(string $tool, array $arguments = []): array
    {
        $response = $this->tool($tool, $arguments)->assertOk();

        return json_decode((string) ((fn (): array => $this->content())->call($response)[0] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_a_parameter_the_tool_does_not_have_is_refused_with_a_suggestion(): void
    {
        $this->tool('list_tasks', ['assignee_mail' => 'x'])->assertHasErrors(['`assignee_mail` is not a parameter of list_tasks']);
        $this->tool('list_tasks', ['assignee_mail' => 'x'])->assertHasErrors(['Did you mean `assignee`?']);
    }

    public function test_the_club_db_case_todo_ids_on_export_plan_is_refused_instead_of_giving_the_whole_plan(): void
    {
        $this->tool('export_plan', ['todo_ids' => ["t-{$this->todoA->id}"]])->assertHasErrors(['`todo_ids` is not a parameter of export_plan', 'milestone_ids']);
    }

    public function test_an_unknown_field_inside_a_task_of_update_tasks_names_its_path(): void
    {
        $this->tool('update_tasks', ['tasks' => [['id' => "k-{$this->open->id}", 'assignee' => 'a@b.ch']]])
            ->assertHasErrors(['`tasks[0].assignee` is not a parameter of update_tasks', 'Did you mean `tasks[0].assigneeEmail`?']);

        $this->assertNull($this->open->fresh()->assigned_user_id === $this->owner->id ? null : 'changed');
    }

    public function test_an_id_of_the_wrong_kind_says_what_it_is(): void
    {
        $this->tool('list_tasks', ['ids' => ["t-{$this->todoA->id}"]])->assertHasErrors(["t-{$this->todoA->id} is a todo, not a task", 'task IDs (k-<number>)', 'export_plan']);
        $this->tool('list_tasks', ['ids' => ["m-{$this->first->id}"]])->assertHasErrors(['is a milestone, not a task']);
        $this->tool('list_tasks', ['milestone_ids' => ["k-{$this->open->id}"]])->assertHasErrors(['is a task, not a milestone', 'milestone IDs (m-<number>)']);
        $this->tool('export_plan', ['milestone_ids' => ["t-{$this->todoA->id}"]])->assertHasErrors(['is a todo, not a milestone']);
        $this->tool('list_comments', ['task_id' => "t-{$this->todoA->id}"])->assertHasErrors(['is a todo, not a task']);
        $this->tool('add_comment', ['task_id' => "m-{$this->first->id}", 'text' => 'Hallo'])->assertHasErrors(['is a milestone, not a task']);
        $this->tool('update_tasks', ['tasks' => [['id' => "t-{$this->todoA->id}", 'status' => 'done']]])->assertHasErrors(['tasks[0].id', 'is a todo, not a task']);
    }

    public function test_something_that_is_no_id_and_a_new_id_of_a_document_are_refused_too(): void
    {
        $this->tool('list_tasks', ['ids' => ['abc']])->assertHasErrors(['"abc" is not an ID']);
        $this->tool('list_tasks', ['ids' => ['k-new-1']])->assertHasErrors(['new item of a document']);
        $this->tool('list_tasks', ['ids' => ["k-{$this->open->id}", (string) $this->done->id]])->assertOk();
    }

    public function test_export_plan_filters_all_make_the_plan_smaller(): void
    {
        $this->other->forceFill(['status' => TaskStatus::DONE, 'commit' => 'abc1234'])->save();
        $this->second->forceFill(['release_version' => '0.9', 'release_status' => ReleaseStatus::RELEASED])->save();
        $whole = $this->data('export_plan');
        $count = fn (array $plan): int => collect($plan['document']['milestones'])->flatMap(fn ($m) => collect($m['todos'])->flatMap(fn ($t) => $t['tasks']))->count();

        $this->assertSame(3, $count($whole));

        foreach ([
            'milestone_ids' => ['milestone_ids' => ["m-{$this->first->id}"]],
            'task_status' => ['task_status' => ['open']],
            'include_released' => ['include_released' => false],
            'version' => ['version' => '0.9'],
        ] as $filter => $arguments) {
            $this->assertLessThan(3, $count($this->data('export_plan', $arguments)), "export_plan: {$filter} does not narrow the answer");
        }
    }

    public function test_list_tasks_filters_all_make_the_answer_smaller(): void
    {
        $this->assertCount(3, $this->data('list_tasks')['tasks']);

        foreach ([
            'status' => ['status' => ['open']],
            'assignee' => ['assignee' => 'me'],
            'milestone_ids' => ['milestone_ids' => ["m-{$this->second->id}"]],
            'query' => ['query' => 'Lampe'],
            'ids' => ['ids' => ["k-{$this->done->id}"]],
            'limit' => ['limit' => 1],
        ] as $filter => $arguments) {
            $this->assertLessThan(3, count($this->data('list_tasks', $arguments)['tasks']), "list_tasks: {$filter} does not narrow the answer");
        }
    }

    public function test_list_comments_filters_all_make_the_answer_smaller(): void
    {
        $comments = [
            Comment::factory()->for($this->open, 'task')->create(),
            Comment::factory()->for($this->done, 'task')->create(),
            Comment::factory()->for($this->other, 'task')->create(),
        ];
        $this->assertCount(3, $this->data('list_comments')['comments']);

        foreach ([
            'task_id' => ['task_id' => "k-{$this->open->id}"],
            'task_status' => ['task_status' => ['done']],
            'milestone_ids' => ['milestone_ids' => ["m-{$this->second->id}"]],
            'comment_id' => ['comment_id' => (string) $comments[0]->id],
            'limit' => ['limit' => 1],
        ] as $filter => $arguments) {
            $this->assertLessThan(3, count($this->data('list_comments', $arguments)['comments']), "list_comments: {$filter} does not narrow the answer");
        }
    }

    public function test_list_ideas_and_list_users_filters_make_the_answer_smaller(): void
    {
        $mine = Idea::factory()->create(['user_id' => $this->owner->id, 'title' => 'Eins']);
        Idea::factory()->create(['user_id' => $this->owner->id, 'title' => 'Zwei', 'project_id' => $this->project->id]);
        $this->assertCount(2, $this->data('list_ideas')['ideas']);

        $this->assertCount(1, $this->data('list_ideas', ['idea_id' => (string) $mine->id])['ideas']);
        $this->assertCount(1, $this->data('list_ideas', ['project_id' => (string) $this->project->id])['ideas']);
        $this->assertCount(0, $this->data('list_ideas', ['status' => 'adopted'])['ideas']);

        $all = $this->data('list_users')['data'];
        $this->assertGreaterThan(count($this->data('list_users', ['query' => 'oliver'])['data']), count($all));
        $this->assertGreaterThan(count($this->data('list_users', ['limit' => 1])['data']), count($all));
    }
}
