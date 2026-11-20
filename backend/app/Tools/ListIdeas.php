<?php

namespace App\Tools;

use App\Models\User;
use App\Planer\RichText\TiptapToMarkdown;

class ListIdeas extends Tool
{
    public function name(): string
    {
        return 'list_ideas';
    }

    public function description(): string
    {
        return 'Lists the private ideas of the person whose token you use, newest first (title, description as Markdown, project, status open, adopted or discarded, whether a draft is saved). Only status open by default.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    private const QUERY = <<<'GRAPHQL'
        query ($status: IdeaStatus) {
          ideas(status: $status, first: 40) {
            data { id title description status draft createdAt project { id name } }
          }
        }
        GRAPHQL;

    public function schema(): array
    {
        return Schema::input([
            'status' => Schema::string('Default open.', ['open', 'adopted', 'discarded']),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            ['status' => ['sometimes', 'in:open,adopted,discarded']],
            ['status.in' => 'status is open (default), adopted or discarded.'],
        );

        $ideas = $this->graphql($actor, self::QUERY, ['status' => strtoupper($validated['status'] ?? 'open')])['ideas']['data'];

        return ToolResult::json(array_map(fn (array $idea): array => [
            ...$idea,
            'description' => $idea['description'] === null ? null : TiptapToMarkdown::convert($idea['description']),
            'draft' => $idea['draft'] !== null,
            'status' => strtolower($idea['status']),
        ], $ideas));
    }
}
