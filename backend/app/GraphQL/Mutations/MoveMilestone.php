<?php

namespace App\GraphQL\Mutations;

use App\Models\Milestone;
use App\Services\MilestoneOrder;
use Illuminate\Support\Facades\DB;

/**
 * Moves a milestone to another place in its project (the order of the
 * versions). The others keep their relative order; the positions are
 * renumbered from 0. Only the position changes.
 */
class MoveMilestone
{
    /**
     * @param  array{id: int|string, position: int}  $args
     */
    public function __invoke(mixed $root, array $args): Milestone
    {
        return DB::transaction(function () use ($args): Milestone {
            $milestone = Milestone::findOrFail($args['id']);

            app(MilestoneOrder::class)->move($milestone, $args['position']);

            return $milestone->refresh();
        });
    }
}
