<?php

namespace App\Observers;

use App\Models\Milestone;
use App\Observers\Concerns\TouchesPlanerRevision;
use App\Services\Ordering;
use App\Services\ReleaseRules;

/**
 * Lifecycle of a milestone in one place: its position in the project and the
 * rules of its release.
 */
class MilestoneObserver
{
    use TouchesPlanerRevision;

    public function __construct(private readonly Ordering $ordering) {}

    /**
     * A new milestone goes to the end of its project, or to the given position;
     * the milestones from there on move one place back.
     */
    public function creating(Milestone $milestone): void
    {
        $group = Milestone::query()->where('project_id', $milestone->project_id);

        if ($milestone->position === null) {
            $milestone->position = $this->ordering->nextPosition($milestone->project_id, $group);

            return;
        }

        $this->ordering->openSlot($milestone->project_id, $group, $milestone->position);
    }

    public function saving(Milestone $milestone): void
    {
        ReleaseRules::checkMilestone($milestone);
    }

    public function created(Milestone $milestone): void
    {
        $this->touchRevision($milestone->project_id);
    }

    public function updated(Milestone $milestone): void
    {
        $this->touchRevision($milestone->project_id);
    }

    /**
     * A deleted milestone leaves no gap. Its todos and tasks go with it
     * (cascade, without events); the whole deletion counts once.
     */
    public function deleted(Milestone $milestone): void
    {
        $this->ordering->close($milestone->project_id, Milestone::query()->where('project_id', $milestone->project_id));
        $this->touchRevision($milestone->project_id);
    }
}
