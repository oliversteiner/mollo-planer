<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use App\Planer\RichText\TiptapToMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_ideas')]
#[Description('Lists the private ideas of the person whose token you use, newest first (title, description as Markdown, project, status open, adopted or discarded, whether a draft is saved). Only status open by default.')]
#[IsReadOnly]
class ListIdeasTool extends Tool
{
    use RunsGraphQL;

    private const QUERY = <<<'GRAPHQL'
        query ($status: IdeaStatus) {
          ideas(status: $status, first: 40) {
            data { id title description status draft createdAt project { id name } }
          }
        }
        GRAPHQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['status' => ['sometimes', 'in:open,adopted,discarded']],
            ['status.in' => 'status is open (default), adopted or discarded.'],
        );

        return $this->respond(function () use ($validated): Response {
            $ideas = app(GraphQLRunner::class)->run(self::QUERY, ['status' => strtoupper($validated['status'] ?? 'open')])['ideas']['data'];

            return Response::json(array_map(fn (array $idea): array => [
                ...$idea,
                'description' => $idea['description'] === null ? null : TiptapToMarkdown::convert($idea['description']),
                'draft' => $idea['draft'] !== null,
                'status' => strtolower($idea['status']),
            ], $ideas));
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['open', 'adopted', 'discarded'])->description('Default open.'),
        ];
    }
}
