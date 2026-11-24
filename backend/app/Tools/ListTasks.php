<?php

namespace App\Tools;

use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planer\RichText\TiptapToMarkdown;
use App\Tools\Support\McpCursor;
use App\Tools\Support\McpText;
use Illuminate\Database\Eloquent\Builder;

class ListTasks extends Tool
{
    /** Comments of a task that "include: comments" brings, and the characters of each. */
    private const COMMENTS = 5;

    private const COMMENT_CHARS = 500;

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'Lists tasks flat and short, with the plan revision. Filter by status, assignee, milestone, title or ids; include adds detail (use with ids).';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID.'),
            'status' => Schema::list(Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']), 'Only these statuses.'),
            'assignee' => Schema::string('"me" or an email.'),
            'milestone_ids' => Schema::list(Schema::string(), 'm-<id> or number.'),
            'query' => Schema::string('Words in the title.'),
            'ids' => Schema::list(Schema::string(), 'k-<id> or number.'),
            'include' => Schema::list(Schema::string(null, ['description', 'comments', 'labels', 'user_story']), 'More per task, use with ids.'),
            'limit' => Schema::integer('1-100, default 30.'),
            'cursor' => Schema::string('next_cursor.'),
        ], ['project_id']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'project_id' => ['required'],
                'status' => ['sometimes', 'array'],
                'status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'assignee' => ['sometimes', 'nullable', 'string', 'max:255'],
                'milestone_ids' => ['sometimes', 'array', 'max:200'],
                'query' => ['sometimes', 'nullable', 'string', 'max:200'],
                'ids' => ['sometimes', 'array', 'max:100'],
                'include' => ['sometimes', 'array'],
                'include.*' => ['in:description,comments,labels,user_story'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
                'cursor' => ['sometimes', 'nullable', 'string', 'max:256'],
            ],
            ['project_id.required' => 'Give project_id (from list_projects).'],
        );

        $project = Project::query()->visibleTo($actor)->whereKey((int) $validated['project_id'])->first();

        if ($project === null) {
            return ToolResult::error('Project not found or not accessible.');
        }

        $limit = (int) ($validated['limit'] ?? 30);
        $cursor = McpCursor::decode($validated['cursor'] ?? null, ['id']);
        $include = $validated['include'] ?? [];
        $assignee = $validated['assignee'] ?? null;

        $tasks = Task::query()
            ->visibleTo($actor)
            ->where('tasks.project_id', $project->getKey())
            ->with(['todo:id,title,milestone_id', 'todo.milestone:id,title', 'assignee:id,email'])
            ->when($include !== [] && in_array('labels', $include, true), fn (Builder $query) => $query->with('labels'))
            ->when(($validated['status'] ?? []) !== [], fn (Builder $query) => $query->whereIn('tasks.status', $validated['status']))
            ->when($assignee === 'me', fn (Builder $query) => $query->where('tasks.assigned_user_id', $actor->getKey()))
            ->when($assignee !== null && $assignee !== 'me', fn (Builder $query) => $query->whereHas('assignee', fn (Builder $users) => $users->where('email', $assignee)))
            ->when(($validated['milestone_ids'] ?? []) !== [], fn (Builder $query) => $query->whereHas('todo', fn (Builder $todos) => $todos->whereIn('milestone_id', array_map(fn ($id): int => (int) preg_replace('/^m-/', '', (string) $id), $validated['milestone_ids']))))
            ->when(($validated['ids'] ?? []) !== [], fn (Builder $query) => $query->whereIn('tasks.id', array_map(fn ($id): int => (int) preg_replace('/^k-/', '', (string) $id), $validated['ids'])))
            ->when(isset($validated['query']) && $validated['query'] !== '', fn (Builder $query) => $query->where('tasks.title', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $validated['query']).'%'))
            ->when($cursor !== null, fn (Builder $query) => $query->where('tasks.id', '>', (int) $cursor['id']))
            ->orderBy('tasks.id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $tasks->count() > $limit;
        $tasks = $tasks->take($limit)->values();
        $comments = in_array('comments', $include, true) ? $this->commentsOf($tasks->modelKeys()) : [];

        $items = $tasks->map(fn (Task $task): array => $this->row($task, $include, $comments[$task->getKey()] ?? []))->all();

        return ToolResult::json(array_filter([
            'revision' => (int) $project->planer_revision,
            'tasks' => $items,
            'has_more' => $hasMore ? true : null,
            'next_cursor' => $hasMore ? McpCursor::encode(['id' => $tasks->last()->getKey()]) : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  list<string>  $include
     * @param  list<array<string, mixed>>  $comments
     * @return array<string, mixed>
     */
    private function row(Task $task, array $include, array $comments): array
    {
        /** @var TaskStatus $status */
        $status = $task->status;

        $row = [
            'id' => 'k-'.$task->getKey(),
            'title' => $task->title,
            'status' => $status->value,
            'todo' => ['id' => 't-'.$task->todo?->getKey(), 'title' => $task->todo?->title],
            'milestone' => ['id' => 'm-'.$task->todo?->milestone?->getKey(), 'title' => $task->todo?->milestone?->title],
            'assigneeEmail' => $task->assignee?->email,
            'commit' => $task->commit,
        ];

        if (in_array('description', $include, true) && $task->description !== null) {
            /** @var array<string, mixed> $document */
            $document = $task->description;
            $text = TiptapToMarkdown::convert($document);
            $row['description'] = $text === '' ? null : $text;
        }

        if (in_array('labels', $include, true)) {
            $row['labels'] = $task->labels->pluck('key')->values()->all();
        }

        if (in_array('user_story', $include, true) && $task->user_story !== null) {
            $row['userStory'] = $task->user_story;
        }

        if ($comments !== []) {
            $row['comments'] = $comments;
        }

        return array_filter($row, fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * The last comments of each given task, newest last, in one query; each text cut short (list_comments has the rest).
     *
     * @param  list<int>  $taskIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function commentsOf(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $grouped = [];

        // The newest COMMENTS of EACH task (a window per task), so one task with many comments does not push the comments
        // of the others out. Still one query, whatever the number of tasks.
        $ranked = Comment::query()
            ->whereIn('task_id', $taskIds)
            ->selectRaw('comments.*, ROW_NUMBER() OVER (PARTITION BY comments.task_id ORDER BY comments.id DESC) as comment_rank');

        Comment::query()
            ->fromSub($ranked, 'comments')
            ->with('author:id,name,is_agent')
            ->where('comment_rank', '<=', self::COMMENTS)
            ->orderByDesc('comments.id')
            ->get()
            ->each(function (Comment $comment) use (&$grouped): void {
                $chunk = McpText::chunk(TiptapToMarkdown::convert($comment->body), 0, self::COMMENT_CHARS);
                $grouped[$comment->task_id][] = array_filter([
                    'id' => (string) $comment->getKey(),
                    'author' => $comment->author?->name,
                    'agent' => $comment->author?->is_agent ? true : null,
                    'text' => $chunk['text'],
                    'truncated' => $chunk['truncated'] ? true : null,
                ], fn (mixed $value): bool => $value !== null);
            });

        return array_map(fn (array $list): array => array_reverse($list), $grouped);
    }
}
