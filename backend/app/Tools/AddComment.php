<?php

namespace App\Tools;

use App\Models\User;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;

class AddComment extends Tool
{
    public function name(): string
    {
        return 'add_comment';
    }

    public function description(): string
    {
        return 'Adds a Markdown comment to a task for short notes. Read comments via list_comments.';
    }

    private const MUTATION = 'mutation ($input: AddCommentInput!) { addComment(input: $input) { id body createdAt author { name isAgent } } }';

    public function schema(): array
    {
        return Schema::input([
            'task_id' => Schema::string('Id of the task, k-<id> or the number.'),
            'text' => Schema::string('The comment in Markdown.'),
        ], ['task_id', 'text']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'task_id' => ['required'],
                'text' => ['required', 'string', 'max:20000'],
            ],
            [
                'task_id.required' => 'Give the task_id (k-<id> or the number).',
                'text.required' => 'Give the text of the comment in Markdown.',
            ],
        );

        $result = MarkdownToTiptap::convert($validated['text'], Mode::Lenient);

        if ($result->doc['content'] === []) {
            return ToolResult::error('The comment is empty.');
        }

        $taskNum = preg_replace('/^k-/', '', (string) $validated['task_id']);
        $comment = $this->graphql($actor, self::MUTATION, ['input' => [
            'taskId' => $taskNum,
            'body' => $result->doc,
        ]])['addComment'];

        $payload = [
            'comment' => [
                'id' => $comment['id'],
                'task_id' => "k-{$taskNum}",
                'text' => TiptapToMarkdown::convert($comment['body']),
                'author' => $comment['author'],
                'created_at' => $comment['createdAt'],
            ],
        ];

        if ($result->warnings !== []) {
            $payload['warnings'] = array_map(fn ($warning): array => $warning->toArray(), $result->warnings);
        }

        return ToolResult::json($payload);
    }
}
