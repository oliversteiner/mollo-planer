<?php

namespace App\GraphQL\Concerns;

use DateTimeInterface;

/**
 * A ProjectPlanInput as Lighthouse resolves it (dates as Carbon, enums
 * as internal values "owner"/"de") turned back into the GraphQL notation
 * of the draft: dates YYYY-MM-DD, enums as names ("OWNER"/"DE").
 */
trait ReadsProjectPlanInput
{
    /**
     * @param  array<string, mixed>  $plan
     * @return array{name: string, description: string|null, locale: string, members: list<array{email: string, role: string}>, milestones: list<array<string, mixed>>}
     */
    protected function planFromInput(array $plan): array
    {
        return [
            'name' => $plan['name'],
            'description' => $plan['description'] ?? null,
            'locale' => mb_strtoupper((string) ($plan['locale'] ?? 'de')),
            'members' => array_map(fn (array $member) => [
                'email' => $member['email'],
                'role' => mb_strtoupper((string) $member['role']),
            ], $plan['members'] ?? []),
            'milestones' => array_map(fn (array $milestone) => [
                ...$this->planItem($milestone),
                'todos' => array_map(fn (array $todo) => [
                    ...$this->planItem($todo),
                    'tasks' => array_map(fn (array $task) => [
                        ...$this->planItem($task),
                        'assigneeEmail' => $task['assigneeEmail'] ?? null,
                    ], $todo['tasks'] ?? []),
                ], $milestone['todos'] ?? []),
            ], $plan['milestones'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, description: string|null, dueDate: string|null}
     */
    private function planItem(array $item): array
    {
        $dueDate = $item['dueDate'] ?? null;

        return [
            'title' => $item['title'],
            'description' => $item['description'] ?? null,
            'dueDate' => $dueDate instanceof DateTimeInterface ? $dueDate->format('Y-m-d') : $dueDate,
        ];
    }
}
