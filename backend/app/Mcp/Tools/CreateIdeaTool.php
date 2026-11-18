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
#[Description('Saves a private idea for the person whose token you use: a title, a description in Markdown (paragraphs, lists, bold, italic, links), optionally a project (project_id) and a draft (a planer-v2 excerpt that the assistant can put into a project later). Use it to keep something that does not belong in a plan yet. Anything in the description that the editor cannot hold becomes plain text; the answer lists those warnings.')]
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
            ['title.required' => 'Give the title of the idea (2 to 200 characters).'],
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
                'draft' => is_array($draft) ? json_encode($draft, JSON_THROW_ON_ERROR) : $draft,
            ], fn ($value): bool => $value !== null)])['createIdea'];

            return Response::json(['idea' => $idea, 'warnings' => $warnings]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Title of the idea, 2 to 200 characters.')->required(),
            'description' => $schema->string()->description('Description in Markdown.'),
            'project_id' => $schema->string()->description('A project of the person (from list_projects), or leave out.'),
            'draft' => $schema->object()->description('A planer-v2 excerpt to put into a project later, or leave out.'),
        ];
    }
}
