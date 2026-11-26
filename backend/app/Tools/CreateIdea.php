<?php

namespace App\Tools;

use App\Models\User;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;

class CreateIdea extends Tool
{
    public function name(): string
    {
        return 'create_idea';
    }

    public function description(): string
    {
        return 'Saves a private idea (optionally with a project or a planer-v2 draft).';
    }

    private const MUTATION = <<<'GRAPHQL'
        mutation ($input: CreateIdeaInput!) {
          createIdea(input: $input) { id title status project { id name } }
        }
        GRAPHQL;

    public function schema(): array
    {
        return Schema::input([
            'title' => Schema::string('Title, 2-200 characters.'),
            'description' => Schema::string('Markdown.'),
            'project_id' => Schema::string('Project ID, or leave out.'),
            'draft' => Schema::object(null, 'planer-v2 excerpt, or leave out.'),
        ], ['title']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
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

        $idea = $this->graphql($actor, self::MUTATION, ['input' => array_filter([
            'title' => $validated['title'],
            'description' => $description,
            'projectId' => isset($validated['project_id']) ? (string) $validated['project_id'] : null,
            'draft' => is_array($draft) ? json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $draft,
        ], fn ($value): bool => $value !== null)])['createIdea'];

        $payload = ['idea' => $idea];

        if ($warnings !== []) {
            $payload['warnings'] = $warnings;
        }

        return ToolResult::json($payload);
    }
}
