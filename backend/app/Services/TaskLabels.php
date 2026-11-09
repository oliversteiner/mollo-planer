<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Which labels a task may get: new ones only from the label set of its
 * project. Labels the task already has may stay after the project changed
 * its set (they are shown, but cannot be chosen again).
 */
class TaskLabels
{
    /**
     * Checks the ids and returns them as integers, without duplicates.
     *
     * @param  list<int|string>  $labelIds
     * @return list<int>
     *
     * @throws ValidationException
     */
    public function validated(Project $project, array $labelIds, ?Task $task, string $field): array
    {
        $ids = array_values(array_unique(array_map('intval', $labelIds)));

        if ($ids === []) {
            return [];
        }

        $allowed = $project->label_set_id === null
            ? collect()
            : $project->labelSet()->firstOrFail()->labels()->pluck('labels.id');

        if ($task !== null) {
            $allowed = $allowed->merge($task->labels()->pluck('labels.id'));
        }

        $allowed = $allowed->map(fn ($id) => (int) $id)->all();

        foreach ($ids as $id) {
            if (! in_array($id, $allowed, true)) {
                throw ValidationException::withMessages([$field => __('labels.not_in_set')]);
            }
        }

        return $ids;
    }
}
