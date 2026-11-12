<?php

namespace App\GraphQL\Mutations;

use App\Models\Milestone;
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

            $order = Milestone::query()
                ->where('project_id', $milestone->project_id)
                ->whereKeyNot($milestone->getKey())
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($order, min($args['position'], count($order)), 0, [$milestone->getKey()]);

            foreach ($order as $position => $id) {
                Milestone::query()->whereKey($id)->update(['position' => $position]);
            }

            return $milestone->refresh();
        });
    }
}
