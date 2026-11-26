<?php

namespace App\GraphQL\Queries;

use App\Services\MilestoneVersionOrder;
use Illuminate\Support\Facades\Auth;

/**
 * Query.milestoneVersionOrder: the preview of "Nach Version ordnen". Visible projects only; the conditions
 * (rights included) are decided by MilestoneVersionOrder::plan().
 */
class MilestoneVersionOrderPlan
{
    public function __construct(private readonly MilestoneVersionOrder $order) {}

    /**
     * @param  array{projectId: int|string}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $project = MilestoneVersionOrder::visibleProject($args['projectId'], Auth::user());

        return $this->order->plan($project, Auth::user());
    }
}
