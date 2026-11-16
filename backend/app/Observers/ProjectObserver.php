<?php

namespace App\Observers;

use App\Models\Project;
use App\Observers\Concerns\TouchesPlanerRevision;

/**
 * A change to the project itself (name, description, locale, label set)
 * changes the planer file. A new project starts at revision 0.
 */
class ProjectObserver
{
    use TouchesPlanerRevision;

    public function updated(Project $project): void
    {
        $this->touchRevision($project->getKey());
    }
}
