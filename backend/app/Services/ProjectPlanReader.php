<?php

namespace App\Services;

use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\GraphQLRunner;

/**
 * Reads what a plan import needs from the app, as the current user: the
 * project tree with ids and titles, its members and the accounts by e-mail.
 * Only reads; ProjectPlanImporter writes.
 */
class ProjectPlanReader
{
    public function __construct(
        private readonly GraphQLRunner $graphQL,
    ) {}

    /**
     * The project with its milestones, todos and tasks (titles and ids).
     *
     * @return array{members: list<array{user: array{id: string, email: string}}>, milestones: list<array{id: string, title: string, todos: list<array{id: string, title: string, tasks: list<array{id: string, title: string}>}>}>}
     *
     * @throws GraphQLFailed
     */
    public function tree(string $projectId): array
    {
        $project = $this->graphQL->runNamed('ProjectPlanTree', ['id' => $projectId])['project'] ?? null;

        if ($project === null) {
            throw new GraphQLFailed('This project does not exist, or you have no access to it. Nothing was created.');
        }

        return $project;
    }

    /**
     * @param  array{milestones: list<array{id: string, title: string, todos: list<array<string, mixed>>}>}  $tree
     * @return array{id: string, title: string, todos: list<array{id: string, title: string, tasks: list<array{id: string, title: string}>}>}
     *
     * @throws GraphQLFailed
     */
    public function findMilestone(array $tree, string $milestoneId): array
    {
        foreach ($tree['milestones'] as $milestone) {
            if ((string) $milestone['id'] === $milestoneId) {
                return $milestone;
            }
        }

        throw new GraphQLFailed('This milestone does not exist in this project. Nothing was created.');
    }

    /**
     * @param  array{milestones: list<array{id: string, title: string, todos: list<array<string, mixed>>}>}  $tree
     * @return array{id: string, title: string, tasks: list<array{id: string, title: string}>}
     *
     * @throws GraphQLFailed
     */
    public function findTodo(array $tree, string $todoId): array
    {
        foreach ($tree['milestones'] as $milestone) {
            foreach ($milestone['todos'] as $todo) {
                if ((string) $todo['id'] === $todoId) {
                    return $todo;
                }
            }
        }

        throw new GraphQLFailed('This todo does not exist in this project. Nothing was created.');
    }

    /**
     * @return array<string, string> Member ids by lower-case e-mail
     *
     * @throws GraphQLFailed
     */
    public function membersByEmail(string $projectId): array
    {
        return $this->membersFromTree($this->tree($projectId));
    }

    /**
     * The members of an already loaded project tree, so the tree is read once.
     *
     * @param  array{members?: list<array{user: array{id: string, email: string}}>}  $tree
     * @return array<string, string> Member ids by lower-case e-mail
     */
    public function membersFromTree(array $tree): array
    {
        return collect($tree['members'] ?? [])
            ->mapWithKeys(fn (array $member) => [self::normalizeEmail($member['user']['email']) => $member['user']['id']])
            ->all();
    }

    /**
     * @return array<string, string> Ids of all accounts by lower-case e-mail
     */
    public function accountIdsByEmail(): array
    {
        $users = $this->graphQL->runNamed('UserEmails')['users'] ?? [];

        return collect($users)->mapWithKeys(fn (array $user) => [self::normalizeEmail($user['email']) => $user['id']])->all();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
