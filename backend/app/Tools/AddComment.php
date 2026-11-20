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
        return 'Adds a comment to a task. text is Markdown with the small whitelist of the app (paragraphs, headings level 2 and 3, lists, quotes, bold, italic, inline code, links); anything else becomes plain text and is named in "warnings". The author is the account of your token. You need the right to comment on the task (the same as in the app). Use it for short notes on a task instead of files. Read them with list_comments.';
    }

    private const MUTATION = 'mutation ($input: AddCommentInput!) { addComment(input: $input) { id createdAt body author { name email isAgent } } }';

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

        $comment = $this->graphql($actor, self::MUTATION, ['input' => [
            'taskId' => preg_replace('/^k-/', '', (string) $validated['task_id']),
            'body' => $result->doc,
        ]])['addComment'];

        return ToolResult::json([
            'comment' => [
                'id' => $comment['id'],
                'task_id' => 'k-'.preg_replace('/^k-/', '', (string) $validated['task_id']),
                'author' => $comment['author'],
                'created_at' => $comment['createdAt'],
                'text' => TiptapToMarkdown::convert($comment['body']),
            ],
            'warnings' => array_map(fn ($warning): array => $warning->toArray(), $result->warnings),
        ]);
    }
}
