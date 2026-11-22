<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_templates')]
#[Description('Lists available project templates compactly (id, key, name, locale, isBuiltin). Set include_details: true for labels and outline.')]
#[IsReadOnly]
class ListTemplatesTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'include_details' => ['sometimes', 'boolean'],
        ]);

        $includeDetails = (bool) ($validated['include_details'] ?? false);

        return $this->respond(function () use ($includeDetails): Response {
            $templates = $this->operation('ListTemplates', ['includeDetails' => $includeDetails])['templates'] ?? [];

            $slim = array_map(function (array $template) use ($includeDetails): array {
                $item = [
                    'id' => $template['id'] ?? null,
                    'key' => $template['key'] ?? null,
                    'name' => $template['name'] ?? null,
                    'locale' => $template['locale'] ?? null,
                    'is_builtin' => $template['isBuiltin'] ?? null,
                    'project_count' => $template['projectCount'] ?? null,
                ];

                if (! empty($template['description'])) {
                    $item['description'] = Str::limit((string) $template['description'], $includeDetails ? 500 : 160);
                }

                if ($includeDetails) {
                    if (isset($template['labels'])) {
                        $item['labels'] = array_map(fn (array $label): array => [
                            'key' => $label['key'] ?? null,
                            'name' => $label['name'] ?? null,
                            'category' => $label['category'] ?? null,
                        ], $template['labels']);
                    }

                    if (isset($template['outline'])) {
                        $item['outline'] = $template['outline'];
                    }
                }

                return $item;
            }, $templates);

            return Response::text(json_encode(
                $slim,
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
            'include_details' => $schema->boolean()->description('true: include labels and outline. Default false to save tokens.'),
        ];
    }
}
