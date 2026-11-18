<?php

namespace App\Assistant;

/**
 * The JSON schema of the answer of Claude (structured output), made from spec/planer-v2.schema.json
 * (docs/auftrag-m6.md §4.2): the excerpt `plan` has the fields of the format that the assistant may set, and
 * nothing else (no revision, changed, members, release, commit, status of milestone or todo, no icon; members and releases are the server's and the person's business, and the grammar the API builds from a bigger schema is refused as too large). No second schema is kept by hand;
 * AssistantSchemaTest compares the fields with spec/.
 *
 * What the API takes of JSON Schema for structured output: objects with `additionalProperties: false` and all
 * properties required, enum, const, anyOf, arrays, `$defs`, and the string formats date and email. It does not take
 * minLength, maxLength, minimum, maxItems, pattern or other formats; they are left out here and the server checks
 * them (Validator) after the answer. An optional field is written as required with the type null, so the limit of
 * optional parameters of the API never comes into play.
 */
final class AssistantSchema
{
    /** The fields of each object the assistant sets, by the name of its definition in the spec. */
    public const FIELDS = [
        '#' => ['project', 'milestones'],
        'project' => ['name', 'description', 'locale', 'labels'],
        'member' => ['email', 'role'],
        'label' => ['key', 'names', 'category', 'color'],
        'milestone' => ['id', 'title', 'description', 'dueDate', 'todos'],
        'todo' => ['id', 'title', 'description', 'dueDate', 'tasks'],
        'task' => ['id', 'title', 'description', 'dueDate', 'status', 'assigneeEmail', 'labels', 'userStory'],
        'userStory' => ['role', 'goal', 'benefit'],
    ];

    private const KEEP = ['type', 'enum', 'const', 'items', 'properties', 'required', 'additionalProperties', 'description', 'anyOf'];

    private const FORMATS = ['date', 'email'];

    /**
     * The schema of the whole answer: reply, choices, plan (an excerpt or null).
     *
     * @return array<string, mixed>
     */
    public static function answer(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['reply', 'choices', 'plan'],
            'properties' => [
                'reply' => ['type' => 'string', 'description' => 'Two to four short sentences for the chat, in the language of the person.'],
                'choices' => [
                    'type' => 'array',
                    'description' => 'A question with buttons, or an empty list when nothing needs to be asked.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['kind', 'label', 'detail', 'value', 'recommended'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => PlanChoices::KINDS],
                            'label' => ['type' => 'string'],
                            'detail' => ['type' => ['string', 'null']],
                            'value' => ['type' => ['string', 'null'], 'description' => 'Id of the project, template, milestone or todo, if the kind has one.'],
                            'recommended' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'plan' => [...self::plan(), 'type' => ['object', 'null'], 'description' => 'The excerpt of the plan to add, or null while information is missing.'],
            ],
        ];
    }

    /**
     * The excerpt: the planer-v2 document reduced to what the assistant sets.
     *
     * @return array<string, mixed>
     */
    public static function plan(): array
    {
        $spec = json_decode((string) file_get_contents(base_path('../spec/planer-v2.schema.json')), true, 512, JSON_THROW_ON_ERROR);

        return self::object($spec, $spec, '#');
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function object(array $spec, array $node, string $name): array
    {
        $properties = [];
        $required = [];

        foreach (self::FIELDS[$name] as $field) {
            $property = $node['properties'][$field];
            $isRequired = in_array($field, $node['required'] ?? [], true);
            $converted = self::convert($spec, $property, $field);

            $properties[$field] = $isRequired ? $converted : self::nullable($converted);
            $required[] = $field;
        }

        return ['type' => 'object', 'additionalProperties' => false, 'required' => $required, 'properties' => $properties];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function convert(array $spec, array $node, string $field): array
    {
        $name = null;

        if (isset($node['$ref'])) {
            $name = basename($node['$ref']);
            $node = [...$spec['$defs'][$name], ...array_diff_key($node, ['$ref' => 1])];
        }

        if (($node['type'] ?? null) === 'object' || isset($node['properties'])) {
            $key = $name !== null && isset(self::FIELDS[$name]) ? $name : (isset(self::FIELDS[$field]) ? $field : null);

            if ($key !== null) {
                return self::object($spec, $node, $key);
            }

            // An object with free keys (the names of a label per language): de and en.
            return ['type' => 'object', 'additionalProperties' => false, 'required' => ['de', 'en'], 'properties' => ['de' => ['type' => ['string', 'null']], 'en' => ['type' => ['string', 'null']]]];
        }

        if (($node['type'] ?? null) === 'array') {
            return ['type' => 'array', 'items' => self::convert($spec, $node['items'], self::singular($field))];
        }

        $kept = array_intersect_key($node, array_flip(self::KEEP));

        if (isset($node['format']) && in_array($node['format'], self::FORMATS, true)) {
            $kept['format'] = $node['format'];
        }

        // The ids of new elements are the server's business: Claude may give any text.
        if (in_array($name, ['id-milestone', 'id-todo', 'id-task'], true)) {
            $kept = ['type' => 'string', 'description' => 'The id of an existing element (an anchor), or any text for a new one: the app gives new elements their ids.'];
        }

        return $kept;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function nullable(array $schema): array
    {
        if (isset($schema['type']) && is_string($schema['type'])) {
            return [...$schema, 'type' => [$schema['type'], 'null']];
        }

        if (isset($schema['type']) && is_array($schema['type'])) {
            return [...$schema, 'type' => array_values(array_unique([...$schema['type'], 'null']))];
        }

        return ['anyOf' => [$schema, ['type' => 'null']]];
    }

    private static function singular(string $field): string
    {
        return match ($field) {
            'milestones' => 'milestone',
            'todos' => 'todo',
            'tasks' => 'task',
            'members' => 'member',
            'labels' => 'label',
            default => $field,
        };
    }
}
