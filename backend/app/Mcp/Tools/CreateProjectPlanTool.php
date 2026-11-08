<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\PlainTextDocument;
use App\Services\TaskNotifier;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_project_plan')]
#[Description(<<<'TEXT'
Adds milestones with their todos and tasks to an existing project in one step, and assigns tasks to project members by e-mail.
All or nothing: if anything fails (a missing right, an invalid date, an assignee who is not a member), nothing is created and the error says which item.
Milestones and todos may be created by every member; tasks need owner or manager rights (see abilities in list_projects).
Descriptions are plain text: blank lines separate paragraphs, lines starting with "- " become a bullet list.
Dates are YYYY-MM-DD. Statuses are derived automatically (new items are open).
By default no e-mails are sent for the assignments; set notify_assignees to true to send one per assigned task.
TEXT)]
class CreateProjectPlanTool extends Tool
{
    use RunsGraphQL;

    private const MILESTONE = 'mutation ($input: CreateMilestoneInput!) { createMilestone(input: $input) { id } }';

    private const TODO = 'mutation ($input: CreateTodoInput!) { createTodo(input: $input) { id } }';

    private const TASK = 'mutation ($input: CreateTaskInput!) { createTask(input: $input) { id } }';

    public function handle(Request $request, TaskNotifier $notifier): Response
    {
        $title = ['required', 'string', 'min:2', 'max:160'];
        $text = ['nullable', 'string', 'max:5000'];
        $date = ['nullable', 'date_format:Y-m-d'];

        $validated = $request->validate([
            'project_id' => ['required'],
            'notify_assignees' => ['boolean'],
            'milestones' => ['required', 'array', 'min:1', 'max:30'],
            'milestones.*.title' => $title,
            'milestones.*.description' => $text,
            'milestones.*.due_date' => $date,
            'milestones.*.todos' => ['array', 'max:50'],
            'milestones.*.todos.*.title' => $title,
            'milestones.*.todos.*.description' => $text,
            'milestones.*.todos.*.due_date' => $date,
            'milestones.*.todos.*.tasks' => ['array', 'max:50'],
            'milestones.*.todos.*.tasks.*.title' => $title,
            'milestones.*.todos.*.tasks.*.description' => $text,
            'milestones.*.todos.*.tasks.*.due_date' => $date,
            'milestones.*.todos.*.tasks.*.assignee_email' => ['nullable', 'email'],
        ], [
            'milestones.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'milestones.*.todos.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'milestones.*.todos.*.tasks.*.due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
            'milestones.required' => 'Give at least one milestone.',
        ]);

        $create = fn () => DB::transaction(fn () => $this->createPlan($validated));

        return $this->respond(function () use ($validated, $notifier, $create) {
            $counts = ($validated['notify_assignees'] ?? false) ? $create() : $notifier->withoutAssignmentMails($create);

            return Response::json(['created' => $counts, 'project_id' => (string) $validated['project_id']]);
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{milestones: int, todos: int, tasks: int, assigned: int}
     *
     * @throws GraphQLFailed
     */
    private function createPlan(array $plan): array
    {
        $projectId = (string) $plan['project_id'];
        $members = $this->membersByEmail($projectId);
        $counts = ['milestones' => 0, 'todos' => 0, 'tasks' => 0, 'assigned' => 0];

        foreach ($plan['milestones'] as $milestone) {
            $milestoneId = $this->create(self::MILESTONE, 'createMilestone', "Milestone \"{$milestone['title']}\"", [
                'projectId' => $projectId,
                ...$this->fields($milestone),
            ]);
            $counts['milestones']++;

            foreach ($milestone['todos'] ?? [] as $todo) {
                $todoId = $this->create(self::TODO, 'createTodo', "Todo \"{$todo['title']}\"", [
                    'milestoneId' => $milestoneId,
                    ...$this->fields($todo),
                ]);
                $counts['todos']++;

                foreach ($todo['tasks'] ?? [] as $task) {
                    $email = isset($task['assignee_email']) ? mb_strtolower(trim($task['assignee_email'])) : null;

                    if ($email !== null && ! isset($members[$email])) {
                        throw new GraphQLFailed("Task \"{$task['title']}\": {$task['assignee_email']} is not a member of this project. Add them with set_project_member first, or leave the task unassigned.");
                    }

                    $this->create(self::TASK, 'createTask', "Task \"{$task['title']}\"", [
                        'todoId' => $todoId,
                        'assigneeId' => $email === null ? null : $members[$email],
                        ...$this->fields($task),
                    ]);
                    $counts['tasks']++;
                    $counts['assigned'] += $email === null ? 0 : 1;
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws GraphQLFailed with the item that failed
     */
    private function create(string $mutation, string $field, string $item, array $input): string
    {
        try {
            return $this->graphQL($mutation, ['input' => $input])[$field]['id'];
        } catch (GraphQLFailed $failure) {
            throw new GraphQLFailed("{$item}: {$failure->getMessage()}");
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, description: array<string, mixed>|null, dueDate: string|null}
     */
    private function fields(array $item): array
    {
        return [
            'title' => $item['title'],
            'description' => PlainTextDocument::from($item['description'] ?? null),
            'dueDate' => $item['due_date'] ?? null,
        ];
    }

    /**
     * @return array<string, string> Member ids by lower-case e-mail
     *
     * @throws GraphQLFailed
     */
    private function membersByEmail(string $projectId): array
    {
        $project = $this->graphQL('query ($id: ID!) { project(id: $id) { members { user { id email } } } }', ['id' => $projectId])['project'] ?? null;

        if ($project === null) {
            throw new GraphQLFailed('This project does not exist, or you have no access to it.');
        }

        return collect($project['members'])->mapWithKeys(fn (array $member) => [mb_strtolower($member['user']['email']) => $member['user']['id']])->all();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $task = $schema->object([
            'title' => $schema->string()->description('What to do, short.')->required(),
            'description' => $schema->string()->description('Details, plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'assignee_email' => $schema->string()->description('E-mail of a project member who does it.'),
        ]);
        $todo = $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'tasks' => $schema->array()->items($task)->description('Tasks of this todo; each task is one person\'s piece of work.'),
        ]);
        $milestone = $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'todos' => $schema->array()->items($todo),
        ]);

        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects or create_project).')->required(),
            'milestones' => $schema->array()->items($milestone)->description('Milestones in order, each with its todos and tasks.')->required(),
            'notify_assignees' => $schema->boolean()->description('Send an assignment e-mail per task. Default false.'),
        ];
    }
}
