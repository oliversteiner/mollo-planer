<?php

namespace App\GraphQL\Mutations;

use App\Models\LabelSet;
use App\Models\Project;

/**
 * Chooses the label set of a project (or none). Allowed by PROJECT_UPDATE
 * (@can). The tasks keep the labels they have.
 */
class SetProjectLabelSet
{
    /**
     * @param  array{projectId: int|string, labelSetId?: int|string|null}  $args
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $project = Project::findOrFail($args['projectId']);
        $setId = $args['labelSetId'] ?? null;

        $project->labelSet()->associate($setId === null ? null : LabelSet::findOrFail($setId));
        $project->save();

        return $project;
    }
}
