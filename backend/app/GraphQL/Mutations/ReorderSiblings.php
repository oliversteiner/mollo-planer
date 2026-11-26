<?php

namespace App\GraphQL\Mutations;

use App\Services\MilestoneVersionOrder;
use App\Services\SiblingOrder;
use Illuminate\Support\Facades\Auth;

/**
 * Mutation.reorderSiblings (task 47): the order of the milestones, the todos of a milestone or the tasks of a todo.
 */
class ReorderSiblings
{
    public function __construct(private readonly SiblingOrder $order) {}

    /**
     * @param  array{projectId: int|string, level: string, parentId?: int|string|null, ids: list<int|string>, expectedRevision?: int|null, dryRun?: bool|null}  $args
     * @return array{revision: int, moveCount: int, order: list<int|string>, before: list<string>}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $project = MilestoneVersionOrder::visibleProject($args['projectId'], Auth::user());

        $result = $this->order->apply(
            Auth::user(),
            $project,
            $args['level'],
            $args['parentId'] ?? null,
            $args['ids'],
            isset($args['expectedRevision']) ? (int) $args['expectedRevision'] : null,
            (bool) ($args['dryRun'] ?? false),
        );

        return [...$result, 'order' => $args['ids']];
    }
}
