<?php

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Services\ProjectLabels;

/**
 * Sets all labels of a project at once. Allowed by PROJECT_UPDATE (@can),
 * which an archived project refuses.
 */
class UpdateProjectLabels
{
    public function __construct(private readonly ProjectLabels $labels) {}

    /**
     * @param  array{projectId: int|string, labels: list<array<string, mixed>>}  $args
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);

        $this->labels->set($project, $args['labels']);

        return $project;
    }
}
