<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_comment')]
#[Description('Adds a comment to a task. text is Markdown with the small whitelist of the app (paragraphs, headings level 2 and 3, lists, quotes, bold, italic, inline code, links); anything else becomes plain text and is named in "warnings". The author is the account of your token. You need the right to comment on the task (the same as in the app). Use it for short notes on a task instead of files. Read them with list_comments.')]
class AddCommentTool extends Tool
{
    use RunsGraphQL;

    private const MUTATION = 'mutation ($input: AddCommentInput!) { addComment(input: $input) { id createdAt body author { name email isAgent } } }';

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
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
            return Response::error('The comment is empty.');
        }

        return $this->respond(function () use ($validated, $result): Response {
            $comment = app(GraphQLRunner::class)->run(self::MUTATION, ['input' => [
                'taskId' => preg_replace('/^k-/', '', (string) $validated['task_id']),
                'body' => $result->doc,
            ]])['addComment'];

            return Response::json([
                'comment' => [
                    'id' => $comment['id'],
                    'task_id' => 'k-'.preg_replace('/^k-/', '', (string) $validated['task_id']),
                    'author' => $comment['author'],
                    'created_at' => $comment['createdAt'],
                    'text' => TiptapToMarkdown::convert($comment['body']),
                ],
                'warnings' => array_map(fn ($warning): array => $warning->toArray(), $result->warnings),
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Id of the task, k-<id> or the number.')->required(),
            'text' => $schema->string()->description('The comment in Markdown.')->required(),
        ];
    }
}
