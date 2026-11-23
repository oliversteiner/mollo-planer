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
#[Description('Adds a Markdown comment to a task for short notes. Read comments via list_comments.')]
class AddCommentTool extends Tool
{
    use RunsGraphQL;

    private const MUTATION = 'mutation ($input: AddCommentInput!) { addComment(input: $input) { id body createdAt author { name isAgent } } }';

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'task_id' => ['required'],
                'text' => ['required', 'string', 'max:20000'],
            ],
            [
                'task_id.required' => 'Give task_id (k-<id> or number).',
                'text.required' => 'Give comment text in Markdown.',
            ],
        );

        $result = MarkdownToTiptap::convert($validated['text'], Mode::Lenient);

        if ($result->doc['content'] === []) {
            return Response::error('The comment is empty.');
        }

        return $this->respond(function () use ($validated, $result): Response {
            $taskNum = preg_replace('/^k-/', '', (string) $validated['task_id']);
            $comment = app(GraphQLRunner::class)->run(self::MUTATION, ['input' => [
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

            return Response::text(json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Task ID (k-<id> or number).')->required(),
            'text' => $schema->string()->description('Comment in Markdown.')->required(),
        ];
    }
}
