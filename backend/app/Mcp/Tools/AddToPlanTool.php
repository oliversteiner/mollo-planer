<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ProjectPlanFailed;
use App\Services\ProjectPlanImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_to_plan')]
#[Description(<<<'TEXT'
Adds to an existing plan without creating a duplicate. Give exactly one target:
milestone_id with todos (the todos, each with its tasks, are added to that milestone), or todo_id with tasks (the tasks are added to that todo).
All or nothing. If a todo title already exists in the milestone, or a task title already exists in the todo (or appears twice in this call), nothing is created and the error names the item.
The answer lists the ids and titles of everything created. Get the ids of the target from get_project.
Same rights as create_project_plan: milestones and todos need the right to create todos, tasks need owner or manager rights.
Tasks accept status (open or done), assignee_email (a project member) and notify_assignees, as in create_project_plan.
TEXT)]
class AddToPlanTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request, ProjectPlanImporter $importer): Response
    {
        $title = ['required', 'string', 'min:2', 'max:160'];
        $text = ['nullable', 'string', 'max:5000'];
        $date = ['nullable', 'date_format:Y-m-d'];

        $validated = $request->validate([
            'project_id' => ['required'],
            'milestone_id' => ['nullable', 'required_without:todo_id'],
            'todo_id' => ['nullable', 'required_without:milestone_id', 'prohibits:milestone_id'],
            'notify_assignees' => ['boolean'],
            'todos' => ['array', 'max:50', 'required_with:milestone_id'],
            'todos.*.title' => $title,
            'todos.*.description' => $text,
            'todos.*.due_date' => $date,
            'todos.*.tasks' => ['array', 'max:50'],
            'todos.*.tasks.*' => ['array'],
            'todos.*.tasks.*.title' => $title,
            'todos.*.tasks.*.description' => $text,
            'todos.*.tasks.*.due_date' => $date,
            'todos.*.tasks.*.assignee_email' => ['nullable', 'email'],
            'todos.*.tasks.*.status' => ['nullable', Rule::in(['open', 'done'])],
            'tasks' => ['array', 'max:50', 'required_with:todo_id'],
            'tasks.*.title' => $title,
            'tasks.*.description' => $text,
            'tasks.*.due_date' => $date,
            'tasks.*.assignee_email' => ['nullable', 'email'],
            'tasks.*.status' => ['nullable', Rule::in(['open', 'done'])],
        ], [
            'milestone_id.required_without' => 'Give milestone_id (to add todos) or todo_id (to add tasks), not neither.',
            'todo_id.required_without' => 'Give milestone_id (to add todos) or todo_id (to add tasks), not neither.',
            'todo_id.prohibits' => 'Give either milestone_id or todo_id, not both.',
            'todos.required_with' => 'With milestone_id give the todos to add.',
            'tasks.required_with' => 'With todo_id give the tasks to add.',
            'todos.*.title.min' => 'A todo title needs at least 2 characters.',
            'tasks.*.title.min' => 'A task title needs at least 2 characters.',
            'todos.*.tasks.*.title.min' => 'A task title needs at least 2 characters.',
            'todos.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'tasks.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'todos.*.tasks.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'tasks.*.status.in' => 'The status of a task is open or done.',
            'todos.*.tasks.*.status.in' => 'The status of a task is open or done.',
        ]);

        $projectId = (string) $validated['project_id'];
        $notify = $validated['notify_assignees'] ?? false;

        return $this->respond(function () use ($importer, $validated, $projectId, $notify): Response {
            try {
                $created = isset($validated['milestone_id'])
                    ? $importer->addToMilestone($projectId, (string) $validated['milestone_id'], $validated['todos'], $notify)
                    : $importer->addToTodo($projectId, (string) $validated['todo_id'], $validated['tasks'], $notify);
            } catch (ProjectPlanFailed $failure) {
                throw new ProjectPlanFailed($failure->kind, $failure->subject, $this->explain($failure->detail));
            }

            return Response::json(['created' => $created, 'project_id' => $projectId]);
        });
    }

    /**
     * The message of a denied right is the same wording as in the other tools.
     */
    private function explain(string $message): string
    {
        return str_contains($message, 'unauthorized')
            ? 'You may not add to this plan. Nothing was changed.'
            : $message;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $task = $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Details, plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'assignee_email' => $schema->string()->description('E-mail of a project member who does it.'),
            'status' => $schema->string()->enum(['open', 'done'])->description('open (default) or done.'),
        ]);
        $todo = $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'tasks' => $schema->array()->items($task),
        ]);

        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects).')->required(),
            'milestone_id' => $schema->string()->description('Id of the milestone that gets the todos. Use this or todo_id.'),
            'todos' => $schema->array()->items($todo)->description('Todos to add to the milestone, each with its tasks. With milestone_id.'),
            'todo_id' => $schema->string()->description('Id of the todo that gets the tasks. Use this or milestone_id.'),
            'tasks' => $schema->array()->items($task)->description('Tasks to add to the todo. With todo_id.'),
            'notify_assignees' => $schema->boolean()->description('Send an assignment e-mail per task. Default false.'),
        ];
    }
}
