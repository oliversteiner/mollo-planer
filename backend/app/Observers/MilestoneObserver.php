<?php

namespace App\Observers;

use App\Models\Milestone;
use App\Observers\Concerns\TouchesPlanerRevision;
use App\Planer\PlanerLimits;
use App\Services\Ordering;
use App\Services\ReleaseRules;

/**
 * Lifecycle of a milestone in one place: its position in the project and the
 * rules of its release. Deleting moves it to the trash (App\Services\Deletion
 * takes its todos, tasks and comments along); restoring puts it at the end of
 * the project; deleting it for good changes neither order nor planer file.
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
        PlanerLimits::assertMilestoneRoom($milestone->project_id);

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
     * A milestone moved to the trash leaves no gap. Its todos and tasks went
     * with it (Deletion); the whole deletion counts once.
     */
    public function deleted(Milestone $milestone): void
    {
        if ($milestone->isForceDeleting()) {
            return;
        }

        $this->ordering->close($milestone->project_id, Milestone::query()->where('project_id', $milestone->project_id));
        $this->touchRevision($milestone->project_id);
    }

    /** Back from the trash: at the end of the project. */
    public function restoring(Milestone $milestone): void
    {
        $milestone->position = $this->ordering->nextPosition($milestone->project_id, Milestone::query()->where('project_id', $milestone->project_id));
    }

    public function restored(Milestone $milestone): void
    {
        $this->touchRevision($milestone->project_id);
    }

    /** Gone for good, with its children (cascade): the planer file showed none of it. */
    public function forceDeleted(Milestone $milestone): void {}
}
