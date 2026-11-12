<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\GraphQLRunner;
use App\Mcp\Support\PlainTextDocument;
use App\Mcp\Support\ProjectPlanFailed;
use App\Mcp\Support\TaskStatusChange;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates or extends a project plan through the app's own GraphQL mutations,
 * as the current user. Same permissions, validation, status automation and
 * mails as a click in the app; all or nothing in one transaction.
 *
 * Used by the MCP tools and by the AI assistant page.
 *
 * Plan shape (plain-text descriptions, dates YYYY-MM-DD):
 * milestones[] { title, description?, due_date?, position?, todos[] { title,
 * description?, due_date?, tasks[] { title, description?, due_date?,
 * assignee_email?, status? (open or done) } } }
 *
 * Results: the counts, plus items: the ids and titles of what was created,
 * so a follow-up call needs no search.
 *
 * @phpstan-type PlanTask array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, assignee_email?: string|null, status?: string|null}
 * @phpstan-type PlanTodo array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, tasks?: list<PlanTask>}
 * @phpstan-type PlanMilestone array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, position?: int|null, todos?: list<PlanTodo>}
 * @phpstan-type PlanCounts array{milestones: int, todos: int, tasks: int, assigned: int, items: array<string, mixed>}
 */
class ProjectPlanImporter
{
    public function __construct(
        private readonly GraphQLRunner $graphQL,
        private readonly ProjectPlanReader $reader,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * Creates a project (admins only), adds its members and then the plan.
     *
     * @param  array{name: string, description?: string|null, locale?: string|null}  $project
     * @param  list<array{email: string, role: string}>  $members
     * @param  list<PlanMilestone>  $milestones
     * @return array{project: array{id: string, name: string}, members: int, created: PlanCounts}
     *
     * @throws ProjectPlanFailed
     * @throws GraphQLFailed
     */
    public function createProject(array $project, array $members, array $milestones, bool $notifyAssignees = false): array
    {
        return $this->transaction($notifyAssignees, function () use ($project, $members, $milestones): array {
            $created = $this->graphQL->runNamed('CreateProject', ['input' => [
                'name' => $project['name'],
                'description' => $project['description'] ?? null,
                'locale' => $project['locale'] ?? 'DE',
            ]])['createProject'];

            $ids = $members === [] ? [] : $this->reader->accountIdsByEmail();

            foreach ($members as $member) {
                $userId = $ids[ProjectPlanReader::normalizeEmail($member['email'])]
                    ?? throw new ProjectPlanFailed(ProjectPlanFailed::UNKNOWN_PERSON, $member['email']);

                $this->graphQL->runNamed('SetProjectMember', ['p' => $created['id'], 'u' => $userId, 'r' => $member['role']]);
            }

            return [
                'project' => $created,
                'members' => count($members),
                'created' => $this->createPlan($created['id'], $milestones),
            ];
        });
    }

    /**
     * Adds milestones with their todos and tasks to an existing project.
     *
     * @param  list<PlanMilestone>  $milestones
     * @return PlanCounts
     *
     * @throws ProjectPlanFailed
     * @throws GraphQLFailed
     */
    public function addPlan(string $projectId, array $milestones, bool $notifyAssignees = false): array
    {
        return $this->transaction($notifyAssignees, fn (): array => $this->createPlan($projectId, $milestones));
    }

    /**
     * Adds todos (each with its tasks) to an existing milestone. A todo or
     * task whose title already exists there stops the whole call.
     *
     * @param  list<PlanTodo>  $todos
     * @return PlanCounts
     *
     * @throws ProjectPlanFailed
     * @throws GraphQLFailed
     */
    public function addToMilestone(string $projectId, string $milestoneId, array $todos, bool $notifyAssignees = false): array
    {
        return $this->transaction($notifyAssignees, function () use ($projectId, $milestoneId, $todos): array {
            $tree = $this->reader->tree($projectId);
            $milestone = $this->reader->findMilestone($tree, $milestoneId);
            $members = $this->reader->membersFromTree($tree);

            $existing = array_column($milestone['todos'], 'title');
            $this->guardDuplicates($todos, $existing, ProjectPlanFailed::DUPLICATE_TODO, $milestone['title']);

            foreach ($todos as $todo) {
                $this->guardDuplicates($todo['tasks'] ?? [], [], ProjectPlanFailed::DUPLICATE_TASK, $todo['title']);
            }

            $counts = ['milestones' => 0, 'todos' => 0, 'tasks' => 0, 'assigned' => 0];
            $items = ['todos' => $this->createTodos($milestoneId, $todos, $members, $counts)];

            return [...$counts, 'items' => $items];
        });
    }

    /**
     * Adds tasks to an existing todo. A task whose title already exists in
     * this todo stops the whole call.
     *
     * @param  list<PlanTask>  $tasks
     * @return PlanCounts
     *
     * @throws ProjectPlanFailed
     * @throws GraphQLFailed
     */
    public function addToTodo(string $projectId, string $todoId, array $tasks, bool $notifyAssignees = false): array
    {
        return $this->transaction($notifyAssignees, function () use ($projectId, $todoId, $tasks): array {
            $tree = $this->reader->tree($projectId);
            $todo = $this->reader->findTodo($tree, $todoId);
            $members = $this->reader->membersFromTree($tree);

            $this->guardDuplicates($tasks, array_column($todo['tasks'], 'title'), ProjectPlanFailed::DUPLICATE_TASK, $todo['title']);

            $counts = ['milestones' => 0, 'todos' => 0, 'tasks' => 0, 'assigned' => 0];
            $items = ['tasks' => $this->createTasks($todoId, $tasks, $members, $counts)];

            return [...$counts, 'items' => $items];
        });
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function transaction(bool $notifyAssignees, \Closure $work): mixed
    {
        $create = fn () => DB::transaction($work);

        return $notifyAssignees ? $create() : $this->notifier->withoutAssignmentMails($create);
    }

    /**
     * @param  list<PlanMilestone>  $milestones
     * @return PlanCounts
     */
    private function createPlan(string $projectId, array $milestones): array
    {
        $members = $this->reader->membersByEmail($projectId);
        $counts = ['milestones' => 0, 'todos' => 0, 'tasks' => 0, 'assigned' => 0];
        $items = [];

        foreach ($milestones as $milestone) {
            // The position key is sent only when given: an explicit null fails the integer rule.
            $position = isset($milestone['position']) ? ['position' => $milestone['position']] : [];

            $milestoneId = $this->create('CreateMilestone', 'createMilestone', ProjectPlanFailed::MILESTONE, $milestone['title'], [
                'projectId' => $projectId,
                ...$position,
                ...$this->fields($milestone),
            ]);
            $counts['milestones']++;

            $items[] = [
                'id' => $milestoneId,
                'title' => $milestone['title'],
                'todos' => $this->createTodos($milestoneId, $milestone['todos'] ?? [], $members, $counts),
            ];
        }

        return [...$counts, 'items' => ['milestones' => $items]];
    }

    /**
     * @param  list<PlanTodo>  $todos
     * @param  array<string, string>  $members
     * @param  array{milestones: int, todos: int, tasks: int, assigned: int}  $counts
     * @return list<array{id: string, title: string, tasks: list<array{id: string, title: string}>}>
     */
    private function createTodos(string $milestoneId, array $todos, array $members, array &$counts): array
    {
        $items = [];

        foreach ($todos as $todo) {
            $todoId = $this->create('CreateTodo', 'createTodo', ProjectPlanFailed::TODO, $todo['title'], [
                'milestoneId' => $milestoneId,
                ...$this->fields($todo),
            ]);
            $counts['todos']++;

            $items[] = [
                'id' => $todoId,
                'title' => $todo['title'],
                'tasks' => $this->createTasks($todoId, $todo['tasks'] ?? [], $members, $counts),
            ];
        }

        return $items;
    }

    /**
     * @param  list<PlanTask>  $tasks
     * @param  array<string, string>  $members
     * @param  array{milestones: int, todos: int, tasks: int, assigned: int}  $counts
     * @return list<array{id: string, title: string}>
     */
    private function createTasks(string $todoId, array $tasks, array $members, array &$counts): array
    {
        $items = [];

        foreach ($tasks as $task) {
            $email = isset($task['assignee_email']) && trim($task['assignee_email']) !== ''
                ? ProjectPlanReader::normalizeEmail($task['assignee_email'])
                : null;

            if ($email !== null && ! isset($members[$email])) {
                throw new ProjectPlanFailed(ProjectPlanFailed::NOT_A_MEMBER, $task['title'], $task['assignee_email']);
            }

            $taskId = $this->create('CreateTask', 'createTask', ProjectPlanFailed::TASK, $task['title'], [
                'todoId' => $todoId,
                'assigneeId' => $email === null ? null : $members[$email],
                ...$this->fields($task),
            ]);
            if (($task['status'] ?? 'open') === TaskStatus::DONE->value) {
                $this->markDone($taskId, $task['title']);
            }
            $counts['tasks']++;
            $counts['assigned'] += $email === null ? 0 : 1;

            $items[] = ['id' => $taskId, 'title' => $task['title']];
        }

        return $items;
    }

    /**
     * Stops at the first title that already exists or appears twice in the
     * same call. The kind says what the duplicate is (todo or task).
     *
     * @param  list<array{title: string}>  $items
     * @param  list<string>  $existing  Titles already in the parent
     *
     * @throws ProjectPlanFailed
     */
    private function guardDuplicates(array $items, array $existing, string $kind, string $parentTitle): void
    {
        $seen = array_map($this->normalizeTitle(...), $existing);

        foreach ($items as $item) {
            $key = $this->normalizeTitle($item['title']);

            if (in_array($key, $seen, true)) {
                throw new ProjectPlanFailed($kind, $item['title'], $parentTitle);
            }

            $seen[] = $key;
        }
    }

    /**
     * Same path as set_task_status: the moveTask mutation, so the task.move
     * permission and the status automation of the observers apply.
     *
     * @throws ProjectPlanFailed with the task that failed
     */
    private function markDone(string $taskId, string $title): void
    {
        try {
            $this->graphQL->runNamed('MoveTask', TaskStatusChange::variables($taskId, TaskStatus::DONE));
        } catch (GraphQLFailed $failure) {
            throw new ProjectPlanFailed(ProjectPlanFailed::TASK, $title, $failure->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ProjectPlanFailed with the item that failed
     */
    private function create(string $operation, string $field, string $kind, string $title, array $input): string
    {
        try {
            return $this->graphQL->runNamed($operation, ['input' => $input])[$field]['id'];
        } catch (GraphQLFailed $failure) {
            throw new ProjectPlanFailed($kind, $title, $failure->getMessage());
        }
    }

    /**
     * @param  array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null}  $item
     * @return array{title: string, description: array<string, mixed>|null, dueDate: string|null}
     */
    private function fields(array $item): array
    {
        $dueDate = $item['due_date'] ?? null;

        return [
            'title' => $item['title'],
            'description' => PlainTextDocument::from($item['description'] ?? null),
            'dueDate' => $dueDate instanceof DateTimeInterface ? $dueDate->format('Y-m-d') : $dueDate,
        ];
    }

    private function normalizeTitle(string $title): string
    {
        return mb_strtolower(trim($title));
    }
}
