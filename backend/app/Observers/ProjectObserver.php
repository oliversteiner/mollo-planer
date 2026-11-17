<?php

namespace App\Observers;

use App\Models\Project;
use App\Observers\Concerns\TouchesPlanerRevision;

/**
 * A change to the project itself (name, description, locale, label set)
 * changes the planer file. A new project starts at revision 0. Archiving
 * and bringing back are not in the file and do not count.
 */
class ProjectObserver
{
    use TouchesPlanerRevision;

    /** Columns whose change alone leaves the planer file as it is. */
    private const NOT_IN_FILE = ['archived_at', 'updated_at'];

    public function updated(Project $project): void
    {
        if (array_diff(array_keys($project->getChanges()), self::NOT_IN_FILE) === []) {
            return;
        }

        $this->touchRevision($project->getKey());
    }
}
