<?php

namespace App\Tools;

use App\Models\User;
use Illuminate\Support\Str;

class ListTemplates extends Tool
{
    public function name(): string
    {
        return 'list_templates';
    }

    public function description(): string
    {
        return 'Lists available project templates compactly (id, key, name, locale, isBuiltin). Set include_details: true for labels and outline.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'include_details' => Schema::boolean('true: include labels and outline. Default false to save tokens.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate($arguments, ['include_details' => ['sometimes', 'boolean']]);
        $includeDetails = (bool) ($validated['include_details'] ?? false);

        $templates = $this->operation($actor, 'ListTemplates', ['includeDetails' => $includeDetails])['templates'] ?? [];

        return ToolResult::json(array_map(function (array $template) use ($includeDetails): array {
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
        }, $templates));
    }
}
