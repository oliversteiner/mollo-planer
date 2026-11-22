<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_idea')]
#[Description('Saves a private idea (title, Markdown description, optional project_id or planer-v2 draft).')]
class CreateIdeaTool extends Tool
{
    use RunsGraphQL;

    private const MUTATION = <<<'GRAPHQL'
        mutation ($input: CreateIdeaInput!) {
          createIdea(input: $input) { id title status project { id name } }
        }
        GRAPHQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'title' => ['required', 'string', 'min:2', 'max:200'],
                'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'project_id' => ['sometimes', 'nullable'],
                'draft' => ['sometimes', 'nullable'],
            ],
            ['title.required' => 'Give title (2 to 200 chars).'],
        );

        $warnings = [];
        $description = null;

        if (! empty($validated['description'])) {
            $result = MarkdownToTiptap::convert($validated['description'], Mode::Lenient);
            $description = $result->doc['content'] === [] ? null : $result->doc;
            $warnings = array_map(fn ($warning): array => $warning->toArray(), $result->warnings);
        }

        $draft = $validated['draft'] ?? null;

        return $this->respond(function () use ($validated, $description, $draft, $warnings): Response {
            $idea = app(GraphQLRunner::class)->run(self::MUTATION, ['input' => array_filter([
                'title' => $validated['title'],
                'description' => $description,
                'projectId' => isset($validated['project_id']) ? (string) $validated['project_id'] : null,
                'draft' => is_array($draft) ? json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $draft,
            ], fn ($value): bool => $value !== null)])['createIdea'];

            $payload = ['idea' => $idea];
            if ($warnings !== []) {
                $payload['warnings'] = $warnings;
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
            'title' => $schema->string()->description('Idea title (2-200 chars).')->required(),
            'description' => $schema->string()->description('Description in Markdown.'),
            'project_id' => $schema->string()->description('Project ID (from list_projects) or omit.'),
            'draft' => $schema->object()->description('Optional planer-v2 excerpt.'),
        ];
    }
}
