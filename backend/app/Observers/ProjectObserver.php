<?php

namespace App\Observers;

use App\Models\Project;
use App\Observers\Concerns\TouchesPlanerRevision;

/**
 * A change to the project itself (name, description, locale, label set)
 * changes the planer file. A new project starts at revision 0. Archiving
 * and bringing back are not in the file and do not count. Moving it to the
 * trash and back counts once each.
 */
class ProjectObserver
{
    use TouchesPlanerRevision;

    /** Columns whose change alone leaves the planer file as it is. */
    private const NOT_IN_FILE = ['archived_at', 'updated_at', 'deleted_at', 'deleted_by', 'deletion_id'];

    public function updated(Project $project): void
    {
        if (array_diff(array_keys($project->getChanges()), self::NOT_IN_FILE) === []) {
            return;
        }

        $this->touchRevision($project->getKey());
    }

    /** To the trash and back: counted once each (Deletion). Deleting it for good: not. */
    public function deleted(Project $project): void
    {
        if (! $project->isForceDeleting()) {
            $this->touchRevision($project->getKey());
        }
    }

    public function restored(Project $project): void
    {
        $this->touchRevision($project->getKey());
    }
}
