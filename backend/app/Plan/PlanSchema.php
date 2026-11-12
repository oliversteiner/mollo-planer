<?php

namespace App\Plan;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The shape of a project plan (milestones, todos, tasks) as the AI sees it:
 * the answer schema of the chat assistant and the input schemas of the MCP
 * tools. The limits live here; graphql/plan.graphql repeats them as @rules,
 * and a test checks that both agree.
 */
final class PlanSchema
{
    public const ROLES = ['OWNER', 'MANAGER', 'MEMBER'];

    public const TASK_STATUSES = ['open', 'done'];

    public const TITLE_MIN = 2;

    public const TITLE_MAX = 160;

    public const TEXT_MAX = 5000;

    public const PLAN_NAME_MAX = 120;

    public const PLAN_DESCRIPTION_MAX = 2000;

    public const MAX_MEMBERS = 50;

    public const MAX_MILESTONES = 30;

    public const MAX_TODOS = 50;

    public const MAX_TASKS = 50;

    /**
     * JSON schema of the chat assistant's answer (structured output).
     *
     * @return array<string, mixed>
     */
    public static function claude(): array
    {
        $text = ['type' => ['string', 'null']];
        $date = ['type' => ['string', 'null'], 'description' => 'YYYY-MM-DD'];
        $object = fn (array $properties): array => [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];

        $task = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'assigneeEmail' => $text]);
        $todo = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'tasks' => ['type' => 'array', 'items' => $task]]);
        $milestone = $object(['title' => ['type' => 'string'], 'description' => $text, 'dueDate' => $date, 'todos' => ['type' => 'array', 'items' => $todo]]);
        $plan = $object([
            'name' => ['type' => 'string'],
            'description' => $text,
            'locale' => ['type' => 'string', 'enum' => ['DE', 'EN']],
            'members' => ['type' => 'array', 'items' => $object([
                'email' => ['type' => 'string'],
                'role' => ['type' => 'string', 'enum' => self::ROLES],
            ])],
            'milestones' => ['type' => 'array', 'items' => $milestone],
        ]);

        return $object([
            'reply' => ['type' => 'string'],
            'plan' => ['anyOf' => [$plan, ['type' => 'null']]],
        ]);
    }

    /**
     * A task for the MCP tools. The title gets a description only where the tool asks for one.
     */
    public static function mcpTask(JsonSchema $schema, ?string $titleDescription = null): Type
    {
        $title = $schema->string();

        if ($titleDescription !== null) {
            $title = $title->description($titleDescription);
        }

        return $schema->object([
            'title' => $title->required(),
            'description' => $schema->string()->description('Details, plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'assignee_email' => $schema->string()->description('E-mail of a project member who does it.'),
            'status' => $schema->string()->enum(self::TASK_STATUSES)->description('open (default) or done.'),
        ]);
    }

    /**
     * A todo for the MCP tools, with its tasks.
     */
    public static function mcpTodo(JsonSchema $schema, Type $task, ?string $tasksDescription = null): Type
    {
        $tasks = $schema->array()->items($task);

        if ($tasksDescription !== null) {
            $tasks = $tasks->description($tasksDescription);
        }

        return $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'tasks' => $tasks,
        ]);
    }

    /**
     * A milestone for create_project_plan, which may also set its place.
     */
    public static function mcpMilestone(JsonSchema $schema, Type $todo, bool $withPosition): Type
    {
        $properties = [];

        if ($withPosition) {
            $properties['position'] = $schema->integer()->description('Place in the project, 0 is the first. Default: the end.');
        }

        return $schema->object([
            ...$properties,
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'todos' => $schema->array()->items($todo),
        ]);
    }
}
