<?php

namespace App\Mcp\Tools;

use App\Services\ProjectPlanImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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

    public function handle(Request $request, ProjectPlanImporter $importer): Response
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

        return $this->respond(fn () => Response::json([
            'created' => $importer->addPlan((string) $validated['project_id'], $validated['milestones'], $validated['notify_assignees'] ?? false),
            'project_id' => (string) $validated['project_id'],
        ]));
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
