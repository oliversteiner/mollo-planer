<?php

namespace App\Services;

use App\Models\Milestone;

/**
 * The order of the milestones inside their project (position, from 0).
 * Used when a milestone is created and when it is moved, so the rule lives
 * in one place.
 */
class MilestoneOrder
{
    /**
     * The place after the last milestone of the project (0 for an empty project).
     */
    public function nextPosition(int|string $projectId): int
    {
        $last = Milestone::query()->where('project_id', $projectId)->max('position');

        return $last === null ? 0 : (int) $last + 1;
    }

    /**
     * Makes room at the given position: the milestones from there on move one place back.
     */
    public function openSlot(int|string $projectId, int $position): void
    {
        Milestone::query()
            ->where('project_id', $projectId)
            ->where('position', '>=', $position)
            ->increment('position');
    }

    /**
     * Puts the milestone at the given place (clamped to the end) and renumbers
     * the whole project from 0. The others keep their relative order.
     */
    public function move(Milestone $milestone, int $position): void
    {
        $order = Milestone::query()
            ->where('project_id', $milestone->project_id)
            ->whereKeyNot($milestone->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        array_splice($order, min($position, count($order)), 0, [$milestone->getKey()]);

        foreach ($order as $index => $id) {
            Milestone::query()->whereKey($id)->update(['position' => $index]);
        }
    }
}
