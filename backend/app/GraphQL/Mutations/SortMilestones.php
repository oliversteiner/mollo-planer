<?php

namespace App\GraphQL\Mutations;

use App\Services\MilestoneVersionOrder;
use Illuminate\Support\Facades\Auth;

/**
 * Mutation.sortMilestones (task 47). The conditions and the rights are checked in
 * MilestoneVersionOrder::apply(), the same place as for the preview.
 */
class SortMilestones
{
    public function __construct(private readonly MilestoneVersionOrder $order) {}

    /**
     * @param  array{projectId: int|string, expectedRevision?: int|null, dryRun?: bool|null}  $args
     * @return array{revision: int, moveCount: int, order: list<int>, before: list<int>}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $project = MilestoneVersionOrder::visibleProject($args['projectId'], Auth::user());

        return $this->order->apply(
            $project,
            Auth::user(),
            isset($args['expectedRevision']) ? (int) $args['expectedRevision'] : null,
            (bool) ($args['dryRun'] ?? false),
        );
    }
}
