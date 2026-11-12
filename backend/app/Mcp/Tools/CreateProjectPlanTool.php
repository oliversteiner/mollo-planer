<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\PlanRules;
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
Dates are YYYY-MM-DD. Milestones and todos get their status automatically. Tasks start open; give a task status done to mark it finished (for example to import completed versions).
Milestones are added at the end unless a position is given (0 is the first); the others move back. The answer lists the ids and titles of everything created, so no search is needed afterwards.
By default no e-mails are sent for the assignments; set notify_assignees to true to send one per assigned task.
TEXT)]
class CreateProjectPlanTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request, ProjectPlanImporter $importer): Response
    {
        $validated = $request->validate([
            'project_id' => ['required'],
            'notify_assignees' => ['boolean'],
            'milestones' => ['required', 'array', 'min:1', 'max:30'],
            ...PlanRules::item('milestones.*'),
            'milestones.*.position' => ['nullable', 'integer', 'min:0'],
            'milestones.*.todos' => ['array', 'max:50'],
            ...PlanRules::item('milestones.*.todos.*'),
            'milestones.*.todos.*.tasks' => ['array', 'max:50'],
            ...PlanRules::task('milestones.*.todos.*.tasks.*'),
        ], [
            ...PlanRules::messages(
                ['milestones.*.due_date', 'milestones.*.todos.*.due_date', 'milestones.*.todos.*.tasks.*.due_date'],
                ['milestones.*.todos.*.tasks.*.status'],
            ),
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
            'status' => $schema->string()->enum(['open', 'done'])->description('open (default) or done.'),
        ]);
        $todo = $schema->object([
            'title' => $schema->string()->required(),
            'description' => $schema->string()->description('Plain text.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD'),
            'tasks' => $schema->array()->items($task)->description('Tasks of this todo; each task is one person\'s piece of work.'),
        ]);
        $milestone = $schema->object([
            'position' => $schema->integer()->description('Place in the project, 0 is the first. Default: the end.'),
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
