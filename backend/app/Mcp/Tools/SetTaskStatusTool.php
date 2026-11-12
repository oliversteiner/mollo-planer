<?php

namespace App\Mcp\Tools;

use App\Enums\TaskStatus;
use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\TaskStatusChange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('set_task_status')]
#[Description('Sets the status of one task, for example done when the work is finished. The status of its todo and milestone is recalculated by the app. Does not change the title, the assignee or anything else, sends no e-mails and deletes nothing.')]
class SetTaskStatusTool extends Tool
{
    use ExplainsGraphQLErrors, RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_id' => ['required'],
            'status' => ['required', Rule::in(self::statuses())],
        ], [
            'task_id.required' => 'Give the task_id, for example from get_project.',
            'status.required' => 'Give the status: open, in_progress, review or done.',
            'status.in' => 'Unknown status. Use one of: open, in_progress, review, done.',
        ]);

        try {
            $task = $this->operation('MoveTask', TaskStatusChange::variables(
                (string) $validated['task_id'],
                TaskStatus::from((string) $validated['status']),
            ))['moveTask'];
        } catch (GraphQLFailed $failure) {
            return Response::error($this->explain($failure->getMessage(), 'You may not change this task. Only members of its project can change task statuses; nothing was changed.', 'This task does not exist, or you have no access to it. Nothing was changed.'));
        }

        return Response::json(TaskStatusChange::summarize($task));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Id of the task (from get_project).')->required(),
            'status' => $schema->string()->enum(self::statuses())->description('open, in_progress, review or done.')->required(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function statuses(): array
    {
        return array_column(TaskStatus::cases(), 'value');
    }
}
