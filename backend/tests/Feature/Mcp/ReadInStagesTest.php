<?php

namespace Tests\Feature\Mcp;

use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * Reading in stages (Auftrag 43 A): the plan from a distance (view outline), without descriptions, and list_tasks.
 */
class ReadInStagesTest extends GraphQLTestCase
{
    private Milestone $second;

    private Task $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task->forceFill(['status' => TaskStatus::DONE, 'title' => 'Hörerprofile schreiben', 'description' => $this->bodyOf('Lang und breit.')])->save();
        $this->second = Milestone::factory()->for($this->project)->create(['title' => 'Zweiter']);
        $todo = Todo::factory()->for($this->second)->create(['title' => 'Aufnahme']);
        $this->other = Task::factory()->for($todo)->create(['title' => 'Mikrofon testen', 'status' => TaskStatus::OPEN, 'assigned_user_id' => $this->users['member']->id, 'board_position' => 1]);
        Task::factory()->for($todo)->create(['title' => 'Schnitt', 'status' => TaskStatus::OPEN, 'board_position' => 2]);
    }

    public function test_the_outline_has_milestones_with_counts_and_nothing_below(): void
    {
        $document = $this->decode(MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'view' => 'outline']))['document'];

        $this->assertTrue($document['excerpt']);
        $this->assertSame('Mollo Planer', $document['project']['name']);
        $this->assertArrayHasKey('revision', $document);
        $this->assertCount(2, $document['milestones']);
        $this->assertSame(['done' => 1], $document['milestones'][0]['tasks']);
        $this->assertSame(['open' => 2], $document['milestones'][1]['tasks']);
        $this->assertSame(1, $document['milestones'][1]['todos']);
        $this->assertStringNotContainsString('Mikrofon', json_encode($document));
        $this->assertStringNotContainsString('description', json_encode($document));
    }

    public function test_the_outline_is_far_smaller_than_the_whole_plan(): void
    {
        $full = json_encode($this->decode(MolloPlanerServer::actingAs($this->users['owner'])->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id])));
        $outline = json_encode($this->decode(MolloPlanerServer::actingAs($this->users['owner'])->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'view' => 'outline'])));

        $this->assertLessThan(strlen($full), strlen($outline));
    }

    public function test_without_descriptions_the_plan_is_an_excerpt_and_a_partial_import_leaves_the_descriptions_alone(): void
    {
        $document = $this->decode(MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id, 'include_descriptions' => false]))['document'];

        $this->assertTrue($document['excerpt']);
        $this->assertStringNotContainsString('"description"', json_encode($document));
        $this->assertStringContainsString('Hörerprofile schreiben', json_encode($document, JSON_UNESCAPED_UNICODE));

        $document['milestones'][0]['todos'][0]['tasks'][0]['title'] = 'Hörerprofile neu';
        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('import_plan'), ['project_id' => (string) $this->project->id, 'plan' => $document, 'mode' => 'partial'])
            ->assertOk();

        $task = $this->task->fresh();
        $this->assertSame('Hörerprofile neu', $task->title);
        $this->assertNotNull($task->description, 'a missing description means unchanged, not empty');
    }

    public function test_the_default_export_still_has_the_descriptions(): void
    {
        $this->assertStringContainsString('Lang und breit.', json_encode($this->decode(MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('export_plan'), ['project_id' => (string) $this->project->id]))));
    }

    public function test_list_tasks_is_flat_and_short(): void
    {
        $result = $this->tasks(['project_id' => (string) $this->project->id]);

        $this->assertSame((int) $this->project->fresh()->planer_revision, $result['revision']);
        $this->assertCount(3, $result['tasks']);
        $row = collect($result['tasks'])->firstWhere('title', 'Mikrofon testen');
        $this->assertSame("k-{$this->other->id}", $row['id']);
        $this->assertSame('open', $row['status']);
        $this->assertSame('Aufnahme', $row['todo']['title']);
        $this->assertSame('Zweiter', $row['milestone']['title']);
        $this->assertSame('m-'.$this->second->id, $row['milestone']['id']);
        $this->assertSame($this->users['member']->email, $row['assigneeEmail']);
        $this->assertArrayNotHasKey('commit', $row, 'empty values are left out');
        $this->assertArrayNotHasKey('description', $row);
        $this->assertArrayNotHasKey('has_more', $result);
    }

    public function test_list_tasks_filters(): void
    {
        $project = (string) $this->project->id;

        $this->assertSame(['Mikrofon testen', 'Schnitt'], array_column($this->tasks(['project_id' => $project, 'status' => ['open']])['tasks'], 'title'));
        $this->assertSame(['Mikrofon testen'], array_column($this->tasks(['project_id' => $project, 'assignee' => 'me'], 'member')['tasks'], 'title'));
        $this->assertSame(['Mikrofon testen'], array_column($this->tasks(['project_id' => $project, 'assignee' => $this->users['member']->email])['tasks'], 'title'));
        $this->assertSame(['Hörerprofile schreiben'], array_column($this->tasks(['project_id' => $project, 'milestone_ids' => ["m-{$this->milestone->id}"]])['tasks'], 'title'));
        $this->assertSame(['Schnitt'], array_column($this->tasks(['project_id' => $project, 'query' => 'schnit'])['tasks'], 'title'));
        $this->assertSame(['Hörerprofile schreiben'], array_column($this->tasks(['project_id' => $project, 'ids' => ["k-{$this->task->id}"]])['tasks'], 'title'));
    }

    public function test_list_tasks_pages_with_a_cursor(): void
    {
        $first = $this->tasks(['project_id' => (string) $this->project->id, 'limit' => 2]);
        $this->assertCount(2, $first['tasks']);
        $this->assertTrue($first['has_more']);

        $second = $this->tasks(['project_id' => (string) $this->project->id, 'limit' => 2, 'cursor' => $first['next_cursor']]);
        $this->assertCount(1, $second['tasks']);
        $this->assertArrayNotHasKey('next_cursor', $second);
    }

    public function test_one_task_with_everything_to_edit_it(): void
    {
        foreach (range(1, 7) as $i) {
            Comment::factory()->for($this->task)->create(['user_id' => $this->users['owner']->id, 'body' => $this->bodyOf("Kommentar $i ".str_repeat('x', 800))]);
        }

        $row = $this->tasks(['project_id' => (string) $this->project->id, 'ids' => ["k-{$this->task->id}"], 'include' => ['description', 'comments']])['tasks'][0];

        $this->assertSame('Lang und breit.', $row['description']);
        $this->assertCount(5, $row['comments']);
        $this->assertStringStartsWith('Kommentar 3 ', $row['comments'][0]['text'], 'the last five, the newest last');
        $this->assertStringStartsWith('Kommentar 7 ', $row['comments'][4]['text']);
        $this->assertLessThanOrEqual(500, mb_strlen($row['comments'][0]['text']));
        $this->assertTrue($row['comments'][0]['truncated']);
    }

    public function test_list_tasks_uses_a_fixed_number_of_queries(): void
    {
        // A first call warms the caches of the rights; then the number of queries is counted twice.
        $this->tasks(['project_id' => (string) $this->project->id, 'include' => ['description', 'comments', 'labels']]);
        DB::enableQueryLog();
        $this->tasks(['project_id' => (string) $this->project->id, 'include' => ['description', 'comments', 'labels']]);
        $few = count(DB::getQueryLog());

        foreach (range(1, 10) as $i) {
            Task::factory()->for($this->todo)->create(['board_position' => 10 + $i]);
        }
        DB::flushQueryLog();
        $this->tasks(['project_id' => (string) $this->project->id, 'include' => ['description', 'comments', 'labels']]);

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_a_stranger_gets_no_tasks(): void
    {
        MolloPlanerServer::actingAs($this->users['stranger'])
            ->tool(McpTools::of('list_tasks'), ['project_id' => (string) $this->project->id])
            ->assertHasErrors();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function tasks(array $arguments, string $as = 'owner'): array
    {
        return $this->decode(MolloPlanerServer::actingAs($this->users[$as])->tool(McpTools::of('list_tasks'), $arguments));
    }

    /** @return array<string, mixed> */
    private function bodyOf(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    /** @return array<string, mixed> */
    private function decode(mixed $response): array
    {
        $response->assertOk();
        $text = (string) ((fn (): array => $this->content())->call($response)[0] ?? '');

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }
}
