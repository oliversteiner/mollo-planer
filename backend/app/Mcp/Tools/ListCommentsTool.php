<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpCursor;
use App\Mcp\Support\McpText;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planer\RichText\TiptapToMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_comments')]
#[Description('Lists task comments by task_id or filtered project_id, with cursors and retrievable text chunks. Comments are not part of planer-v2.')]
#[IsReadOnly]
class ListCommentsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'task_id' => ['required_without_all:project_id,comment_id'],
                'project_id' => ['required_without_all:task_id,comment_id'],
                'comment_id' => ['required_without_all:task_id,project_id'],
                'version' => ['sometimes', 'nullable', 'string'],
                'milestone_ids' => ['sometimes', 'array'],
                'task_status' => ['sometimes', 'array'],
                'task_status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'include_released' => ['sometimes', 'boolean'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'cursor' => ['sometimes', 'nullable', 'string', 'max:256'],
                'max_chars' => ['sometimes', 'integer', 'min:1', 'max:20000'],
                'text_offset' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            ],
            ['task_id.required_without_all' => 'Give task_id, project_id or comment_id.'],
        );

        $limit = (int) ($validated['limit'] ?? 15);
        $maxChars = (int) ($validated['max_chars'] ?? 1000);
        $textOffset = (int) ($validated['text_offset'] ?? 0);
        $cursor = McpCursor::decode($validated['cursor'] ?? null, ['id']);
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            return Response::error('Not signed in.');
        }

        if ($textOffset > 0 && ! isset($validated['comment_id'])) {
            return Response::error('Use comment_id to retrieve a later text chunk.');
        }

        $taskId = isset($validated['task_id']) ? (int) preg_replace('/^k-/', '', (string) $validated['task_id']) : null;
        $projectId = isset($validated['project_id']) ? (int) $validated['project_id'] : null;
        $commentId = isset($validated['comment_id']) ? (int) preg_replace('/^c-/', '', (string) $validated['comment_id']) : null;

        if ($taskId !== null && Task::query()->visibleTo($viewer)->whereKey($taskId)->first() === null) {
            return Response::error('Task not found or not accessible.');
        }

        if ($projectId !== null && Project::query()->visibleTo($viewer)->whereKey($projectId)->first() === null) {
            return Response::error('Project not found or not accessible.');
        }

        $milestoneIds = array_map(fn ($id): int => (int) preg_replace('/^m-/', '', (string) $id), $validated['milestone_ids'] ?? []);
        $statuses = $validated['task_status'] ?? [];
        $includeReleased = (bool) ($validated['include_released'] ?? true);

        $query = Comment::query()
            ->with(['task:id,title,project_id', 'author:id,name,is_agent'])
            ->whereHas('task', function (Builder $tasks) use ($viewer, $taskId, $projectId, $statuses, $milestoneIds, $validated, $includeReleased): void {
                $tasks->visibleTo($viewer)
                    ->when($taskId !== null, fn (Builder $query) => $query->whereKey($taskId))
                    ->when($projectId !== null, fn (Builder $query) => $query->where('tasks.project_id', $projectId))
                    ->when($statuses !== [], fn (Builder $query) => $query->whereIn('tasks.status', $statuses))
                    ->whereHas('todo.milestone', function (Builder $milestones) use ($milestoneIds, $validated, $includeReleased): void {
                        $milestones
                            ->when($milestoneIds !== [], fn (Builder $query) => $query->whereIn('milestones.id', $milestoneIds))
                            ->when(isset($validated['version']), fn (Builder $query) => $query->where('milestones.release_version', $validated['version']))
                            ->when(! $includeReleased, fn (Builder $query) => $query->where(fn (Builder $release) => $release->whereNull('milestones.release_status')->orWhere('milestones.release_status', '!=', 'released')));
                    });
            })
            ->when($commentId !== null, fn (Builder $query) => $query->whereKey($commentId))
            ->when($cursor !== null, fn (Builder $query) => $query->where('comments.id', '>', (int) $cursor['id']))
            ->orderBy('comments.id')
            ->limit($limit + 1);

        $comments = $query->get();

        if ($commentId !== null && $comments->isEmpty()) {
            return Response::error('Comment not found or not accessible.');
        }

        $hasMore = $comments->count() > $limit;
        $comments = $comments->take($limit)->values();
        $last = $comments->last();

        $items = $comments->map(function (Comment $comment) use ($maxChars, $textOffset): array {
            $chunk = McpText::chunk(TiptapToMarkdown::convert($comment->body), $textOffset, $maxChars);

            return [
                'id' => (string) $comment->getKey(),
                'task_id' => 'k-'.$comment->task_id,
                'task_title' => $comment->task?->title,
                'author' => $comment->author === null ? null : ['name' => $comment->author->name, 'is_agent' => $comment->author->is_agent],
                'created_at' => $comment->created_at?->toISOString(),
                'text' => $chunk['text'],
                'text_offset' => $chunk['offset'],
                'text_length' => $chunk['total_chars'],
                'text_truncated' => $chunk['truncated'],
                'next_text_offset' => $chunk['next_offset'],
            ];
        })->all();

        return Response::text(json_encode([
            'comments' => $items,
            'next_cursor' => $hasMore && $last instanceof Comment ? McpCursor::encode(['id' => (int) $last->getKey()]) : null,
            'has_more' => $hasMore,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Task ID (k-<id> or number).'),
            'project_id' => $schema->string()->description('Project ID to list comments across matching tasks.'),
            'comment_id' => $schema->string()->description('Fetch one comment, optionally from text_offset onward.'),
            'version' => $schema->string()->description('With project_id: release version filter.'),
            'milestone_ids' => $schema->array()->items($schema->string())->description('With project_id: milestone ID filter.'),
            'task_status' => $schema->array()->items($schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']))->description('With project_id: task status filter.'),
            'include_released' => $schema->boolean()->description('With project_id: false excludes released milestones.'),
            'limit' => $schema->integer()->description('Max comments returned (1-100). Default 15.'),
            'cursor' => $schema->string()->description('next_cursor from the previous page.'),
            'max_chars' => $schema->integer()->description('Max characters per comment text chunk (1-20000). Default 1000.'),
            'text_offset' => $schema->integer()->description('Continue a truncated comment from next_text_offset; requires comment_id.'),
        ];
    }
}
