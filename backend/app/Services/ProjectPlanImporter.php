<?php

namespace App\Services;

use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\GraphQLRunner;
use App\Mcp\Support\PlainTextDocument;
use App\Mcp\Support\ProjectPlanFailed;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a whole project plan (optionally with the project and its
 * members) through the app's own GraphQL mutations, as the current user.
 * Same permissions, validation, status automation and mails as a click in
 * the app; all or nothing in one transaction.
 *
 * Used by the MCP tools and by the AI assistant page.
 *
 * Plan shape (plain-text descriptions, dates YYYY-MM-DD):
 * milestones[] { title, description?, due_date?, todos[] { title,
 * description?, due_date?, tasks[] { title, description?, due_date?,
 * assignee_email? } } }
 *
 * @phpstan-type PlanTask array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, assignee_email?: string|null}
 * @phpstan-type PlanTodo array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, tasks?: list<PlanTask>}
 * @phpstan-type PlanMilestone array{title: string, description?: string|null, due_date?: string|DateTimeInterface|null, todos?: list<PlanTodo>}
 * @phpstan-type PlanCounts array{milestones: int, todos: int, tasks: int, assigned: int}
 */
class ProjectPlanImporter
{
    private const PROJECT = 'mutation ($input: CreateProjectInput!) { createProject(input: $input) { id name } }';

    private const MEMBER = 'mutation ($p: ID!, $u: ID!, $r: ProjectRole!) { setProjectMember(projectId: $p, userId: $u, role: $r) { id } }';

    private const MILESTONE = 'mutation ($input: CreateMilestoneInput!) { createMilestone(input: $input) { id } }';

    private const TODO = 'mutation ($input: CreateTodoInput!) { createTodo(input: $input) { id } }';

    private const TASK = 'mutation ($input: CreateTaskInput!) { createTask(input: $input) { id } }';

    public function __construct(
        private readonly GraphQLRunner $graphQL,
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
            $created = $this->graphQL->run(self::PROJECT, ['input' => [
                'name' => $project['name'],
                'description' => $project['description'] ?? null,
                'locale' => $project['locale'] ?? 'DE',
            ]])['createProject'];

            $ids = $members === [] ? [] : $this->userIdsByEmail();

            foreach ($members as $member) {
                $userId = $ids[$this->normalize($member['email'])]
                    ?? throw new ProjectPlanFailed(ProjectPlanFailed::UNKNOWN_PERSON, $member['email']);

                $this->graphQL->run(self::MEMBER, ['p' => $created['id'], 'u' => $userId, 'r' => $member['role']]);
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
        $members = $this->membersByEmail($projectId);
        $counts = ['milestones' => 0, 'todos' => 0, 'tasks' => 0, 'assigned' => 0];

        foreach ($milestones as $milestone) {
            $milestoneId = $this->create(self::MILESTONE, 'createMilestone', ProjectPlanFailed::MILESTONE, $milestone['title'], [
                'projectId' => $projectId,
                ...$this->fields($milestone),
            ]);
            $counts['milestones']++;

            foreach ($milestone['todos'] ?? [] as $todo) {
                $todoId = $this->create(self::TODO, 'createTodo', ProjectPlanFailed::TODO, $todo['title'], [
                    'milestoneId' => $milestoneId,
                    ...$this->fields($todo),
                ]);
                $counts['todos']++;

                foreach ($todo['tasks'] ?? [] as $task) {
                    $email = isset($task['assignee_email']) && trim($task['assignee_email']) !== ''
                        ? $this->normalize($task['assignee_email'])
                        : null;

                    if ($email !== null && ! isset($members[$email])) {
                        throw new ProjectPlanFailed(ProjectPlanFailed::NOT_A_MEMBER, $task['title'], $task['assignee_email']);
                    }

                    $this->create(self::TASK, 'createTask', ProjectPlanFailed::TASK, $task['title'], [
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
     * @throws ProjectPlanFailed with the item that failed
     */
    private function create(string $mutation, string $field, string $kind, string $title, array $input): string
    {
        try {
            return $this->graphQL->run($mutation, ['input' => $input])[$field]['id'];
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

    /**
     * @return array<string, string> Member ids by lower-case e-mail
     *
     * @throws GraphQLFailed
     */
    private function membersByEmail(string $projectId): array
    {
        $project = $this->graphQL->run('query ($id: ID!) { project(id: $id) { members { user { id email } } } }', ['id' => $projectId])['project'] ?? null;

        if ($project === null) {
            throw new GraphQLFailed('This project does not exist, or you have no access to it.');
        }

        return collect($project['members'])->mapWithKeys(fn (array $member) => [$this->normalize($member['user']['email']) => $member['user']['id']])->all();
    }

    /**
     * @return array<string, string> Ids of all accounts by lower-case e-mail
     */
    private function userIdsByEmail(): array
    {
        $users = $this->graphQL->run('{ users { id email } }')['users'] ?? [];

        return collect($users)->mapWithKeys(fn (array $user) => [$this->normalize($user['email']) => $user['id']])->all();
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
