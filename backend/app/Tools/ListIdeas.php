<?php

namespace App\Tools;

use App\Enums\Right;
use App\Models\Idea;
use App\Models\User;
use App\Planer\RichText\TiptapToMarkdown;
use App\Support\Rights;
use App\Tools\Support\McpCursor;
use App\Tools\Support\McpText;
use Illuminate\Database\Eloquent\Builder;

class ListIdeas extends Tool
{
    public function name(): string
    {
        return 'list_ideas';
    }

    public function description(): string
    {
        return 'Lists private ideas newest first with pagination and retrievable description chunks. Default status: open, limit: 15.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'status' => Schema::string('Default open.', ['open', 'adopted', 'discarded']),
            'project_id' => Schema::string('Optional project ID filter.'),
            'idea_id' => Schema::string('Fetch one idea, optionally continuing its description.'),
            'limit' => Schema::integer('Max ideas returned (1-50). Default 15.'),
            'max_chars' => Schema::integer('Max description chars per chunk (0 omits text). Default 400.'),
            'cursor' => Schema::string('next_cursor from the previous page.'),
            'description_offset' => Schema::integer('Continue from next_description_offset; requires idea_id.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'status' => ['sometimes', 'in:open,adopted,discarded'],
                'project_id' => ['sometimes', 'nullable'],
                'idea_id' => ['sometimes', 'nullable'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
                'max_chars' => ['sometimes', 'integer', 'min:0', 'max:5000'],
                'cursor' => ['sometimes', 'nullable', 'string', 'max:256'],
                'description_offset' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            ],
            ['status.in' => 'status must be open (default), adopted or discarded.'],
        );

        $limit = (int) ($validated['limit'] ?? 15);
        $maxChars = (int) ($validated['max_chars'] ?? 400);
        $descriptionOffset = (int) ($validated['description_offset'] ?? 0);
        $cursor = McpCursor::decode($validated['cursor'] ?? null, ['created_at', 'id']);

        if ($descriptionOffset > 0 && ! isset($validated['idea_id'])) {
            return ToolResult::error('Use idea_id to retrieve a later description chunk.');
        }

        $canViewAll = Rights::allows($actor, Right::IDEAS_VIEW_ANY);
        $ideas = Idea::query()
            ->with('project:id,name')
            ->when(! $canViewAll, fn (Builder $query) => $query->where('user_id', $actor->getKey()))
            ->when(isset($validated['status']), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(! isset($validated['status']) && ! isset($validated['idea_id']), fn (Builder $query) => $query->where('status', 'open'))
            ->when(isset($validated['project_id']), fn (Builder $query) => $query->where('project_id', $validated['project_id']))
            ->when(isset($validated['idea_id']), fn (Builder $query) => $query->whereKey($validated['idea_id']))
            ->when($cursor !== null && ! isset($validated['idea_id']), fn (Builder $query) => $query->where(function (Builder $after) use ($cursor): void {
                $after->where('ideas.created_at', '<', (string) $cursor['created_at'])
                    ->orWhere(fn (Builder $sameTime) => $sameTime->where('ideas.created_at', (string) $cursor['created_at'])->where('ideas.id', '<', (int) $cursor['id']));
            }))
            ->orderByDesc('ideas.created_at')
            ->orderByDesc('ideas.id')
            ->limit(isset($validated['idea_id']) ? 1 : $limit + 1)
            ->get();

        if (isset($validated['idea_id']) && $ideas->isEmpty()) {
            return ToolResult::error('Idea not found or not accessible.');
        }

        $hasMore = ! isset($validated['idea_id']) && $ideas->count() > $limit;
        $ideas = $ideas->take($limit)->values();
        $last = $ideas->last();

        $items = $ideas->map(function (Idea $idea) use ($maxChars, $descriptionOffset): array {
            $description = $idea->description === null ? '' : TiptapToMarkdown::convert($idea->description);
            $chunk = McpText::chunk($description, $descriptionOffset, $maxChars);

            return [
                'id' => (string) $idea->getKey(),
                'title' => $idea->title,
                'description' => $maxChars === 0 ? null : $chunk['text'],
                'description_offset' => $chunk['offset'],
                'description_length' => $chunk['total_chars'],
                'description_truncated' => $chunk['truncated'],
                'next_description_offset' => $chunk['next_offset'],
                'status' => $idea->status->value,
                'has_draft' => $idea->draft !== null,
                'project' => $idea->project === null ? null : ['id' => (string) $idea->project->getKey(), 'name' => $idea->project->name],
                'created_at' => $idea->created_at?->toISOString(),
            ];
        })->all();

        $createdAt = $last instanceof Idea ? $last->getRawOriginal('created_at') : null;

        return ToolResult::json([
            'ideas' => $items,
            'next_cursor' => $hasMore && $last instanceof Idea ? McpCursor::encode(['created_at' => (string) $createdAt, 'id' => (int) $last->getKey()]) : null,
            'has_more' => $hasMore,
        ]);
    }
}
