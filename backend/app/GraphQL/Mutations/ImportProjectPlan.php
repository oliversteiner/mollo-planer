<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ReadsProjectPlanInput;
use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\ProjectPlanFailed;
use App\Models\Project;
use App\Services\ProjectPlanImporter;
use Illuminate\Validation\ValidationException;

/**
 * Imports a draft of the AI assistant: creates the project, adds the
 * members and the plan, all or nothing (admins only, checked by @can).
 */
class ImportProjectPlan
{
    use ReadsProjectPlanInput;

    public function __construct(private readonly ProjectPlanImporter $importer) {}

    /**
     * @param  array{plan: array<string, mixed>, notifyAssignees?: bool|null}  $args
     *
     * @throws ValidationException naming the item that failed
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $plan = $this->planFromInput($args['plan']);
        $milestones = array_map(fn (array $milestone) => [
            ...$this->importItem($milestone),
            'todos' => array_map(fn (array $todo) => [
                ...$this->importItem($todo),
                'tasks' => array_map(fn (array $task) => [
                    ...$this->importItem($task),
                    'assignee_email' => $task['assigneeEmail'],
                ], $todo['tasks']),
            ], $milestone['todos']),
        ], $plan['milestones']);

        try {
            $result = $this->importer->createProject(
                ['name' => $plan['name'], 'description' => $plan['description'], 'locale' => $plan['locale']],
                $plan['members'],
                $milestones,
                (bool) ($args['notifyAssignees'] ?? false),
            );
        } catch (ProjectPlanFailed $failure) {
            throw ValidationException::withMessages(['plan' => $failure->localized()]);
        } catch (GraphQLFailed $failure) {
            throw ValidationException::withMessages(['plan' => $failure->getMessage()]);
        }

        return Project::findOrFail($result['project']['id']);
    }

    /**
     * @param  array{title: string, description: string|null, dueDate: string|null}  $item
     * @return array{title: string, description: string|null, due_date: string|null}
     */
    private function importItem(array $item): array
    {
        return ['title' => $item['title'], 'description' => $item['description'], 'due_date' => $item['dueDate']];
    }
}
