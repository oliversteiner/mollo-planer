<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\MolloPlanerServer;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Tests\Feature\GraphQL\GraphQLTestCase;
use Tests\McpTools;

/**
 * add_comment and list_comments (task 30 C): the rights of addComment, the whitelist of the rich text, newest last.
 */
class CommentToolsTest extends GraphQLTestCase
{
    public function test_a_member_adds_a_comment_as_himself(): void
    {
        MolloPlanerServer::actingAs($this->users['member'])
            ->tool(McpTools::of('add_comment'), ['task_id' => "k-{$this->task->id}", 'text' => "Erledigt in **abc1234**.\n\n- erstens\n- zweitens"])
            ->assertOk()
            ->assertSee('David Member')
            ->assertSee('**abc1234**');

        $comment = Comment::query()->sole();
        $this->assertSame($this->users['member']->id, $comment->user_id);
        $this->assertSame($this->task->id, $comment->task_id);
    }

    public function test_the_number_alone_is_enough_and_a_stranger_may_not(): void
    {
        MolloPlanerServer::actingAs($this->users['stranger'])
            ->tool(McpTools::of('add_comment'), ['task_id' => (string) $this->task->id, 'text' => 'Hallo'])
            ->assertHasErrors();
        $this->assertSame(0, Comment::query()->count());

        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('add_comment'), ['task_id' => (string) $this->task->id, 'text' => 'Hallo'])
            ->assertOk();
        $this->assertSame(1, Comment::query()->count());
    }

    public function test_what_the_whitelist_does_not_know_becomes_plain_text_with_a_warning(): void
    {
        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('add_comment'), ['task_id' => (string) $this->task->id, 'text' => "| a | b |\n|---|---|\n| 1 | 2 |"])
            ->assertOk()
            ->assertSee('"warnings":[{');
    }

    public function test_an_empty_text_is_refused(): void
    {
        MolloPlanerServer::actingAs($this->users['owner'])
            ->tool(McpTools::of('add_comment'), ['task_id' => (string) $this->task->id, 'text' => '   '])
            ->assertHasErrors();
    }

    public function test_list_comments_of_a_task_shows_the_newest_last_with_author_and_time(): void
    {
        $this->travelTo(now()->subHour());
        Comment::factory()->for($this->task)->create(['user_id' => $this->users['owner']->id, 'body' => $this->bodyOf('Erster')]);
        $this->travelBack();
        Comment::factory()->for($this->task)->create(['user_id' => $this->users['member']->id, 'body' => $this->bodyOf('Zweiter')]);

        $json = $this->decode(MolloPlanerServer::actingAs($this->users['member'])
            ->tool(McpTools::of('list_comments'), ['task_id' => "k-{$this->task->id}"]));

        $comments = $json['comments'];
        $this->assertSame(['Erster', 'Zweiter'], array_column($comments, 'text'));
        $this->assertSame('Ben Owner', $comments[0]['author']['name']);
        $this->assertFalse($comments[0]['author']['is_agent']);
        $this->assertNotEmpty($comments[0]['created_at']);
    }

    public function test_a_comment_of_an_agent_is_marked_as_such(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Claude']);
        Comment::factory()->for($this->task)->create(['user_id' => $agent->id, 'body' => $this->bodyOf('Fertig')]);

        $json = $this->decode(MolloPlanerServer::actingAs($this->users['owner'])->tool(McpTools::of('list_comments'), ['task_id' => (string) $this->task->id]));

        $this->assertTrue($json['comments'][0]['author']['is_agent']);
    }

    public function test_list_comments_of_a_project_uses_the_filters_and_skips_tasks_without_comments(): void
    {
        $other = Milestone::factory()->for($this->project)->create(['position' => 1]);
        $other->forceFill(['release_version' => '2.0', 'release_status' => 'planned'])->save();
        $otherTask = Task::factory()->for(Todo::factory()->for($other)->create())->create(['board_position' => 5]);
        Task::factory()->for($this->todo)->create(['board_position' => 6]);

        Comment::factory()->for($this->task)->create(['body' => $this->bodyOf('A')]);
        Comment::factory()->for($otherTask)->create(['body' => $this->bodyOf('B')]);

        $all = $this->decode(MolloPlanerServer::actingAs($this->users['member'])->tool(McpTools::of('list_comments'), ['project_id' => (string) $this->project->id]));
        $this->assertCount(2, $all['comments']);

        $filtered = $this->decode(MolloPlanerServer::actingAs($this->users['member'])->tool(McpTools::of('list_comments'), ['project_id' => (string) $this->project->id, 'version' => '2.0']));
        $this->assertSame(["k-{$otherTask->id}"], array_column($filtered['comments'], 'task_id'));

        MolloPlanerServer::actingAs($this->users['stranger'])->tool(McpTools::of('list_comments'), ['task_id' => (string) $this->task->id])->assertHasErrors();
    }

    public function test_comments_are_paginatable_and_long_text_can_be_retrieved_in_unicode_chunks(): void
    {
        $text = str_repeat('界', 1200);
        $first = Comment::factory()->for($this->task)->create(['body' => $this->bodyOf($text)]);
        Comment::factory()->for($this->task)->create(['body' => $this->bodyOf('Next comment')]);

        $firstPage = $this->decode(MolloPlanerServer::actingAs($this->users['member'])->tool(McpTools::of('list_comments'), [
            'task_id' => "k-{$this->task->id}", 'limit' => 1, 'max_chars' => 500,
        ]));

        $this->assertTrue($firstPage['has_more']);
        $this->assertSame(500, mb_strlen($firstPage['comments'][0]['text']));
        $this->assertTrue($firstPage['comments'][0]['text_truncated']);

        $secondPage = $this->decode(MolloPlanerServer::actingAs($this->users['member'])->tool(McpTools::of('list_comments'), [
            'task_id' => "k-{$this->task->id}", 'limit' => 1, 'cursor' => $firstPage['next_cursor'],
        ]));
        $this->assertSame('Next comment', $secondPage['comments'][0]['text']);
        $this->assertFalse($secondPage['has_more']);

        $rest = $this->decode(MolloPlanerServer::actingAs($this->users['member'])->tool(McpTools::of('list_comments'), [
            'comment_id' => (string) $first->id, 'text_offset' => $firstPage['comments'][0]['next_text_offset'], 'max_chars' => 800,
        ]));
        $this->assertSame(700, mb_strlen($rest['comments'][0]['text']));
        $this->assertSame($text, $firstPage['comments'][0]['text'].$rest['comments'][0]['text']);
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyOf(string $text): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $response): array
    {
        $response->assertOk();
        $text = (string) ((fn (): array => $this->content())->call($response)[0] ?? '');

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }
}
