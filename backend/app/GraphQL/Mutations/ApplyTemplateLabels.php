<?php

namespace App\GraphQL\Mutations;

use App\Models\Project;
use App\Models\Template;
use App\Services\ProjectLabels;

/**
 * Takes over the labels of a template in a project: replace or append.
 * Allowed by PROJECT_UPDATE (@can), which an archived project refuses.
 */
class ApplyTemplateLabels
{
    public function __construct(private readonly ProjectLabels $labels) {}

    /**
     * @param  array{projectId: int|string, templateId: int|string, mode: string}  $args
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $project = Project::query()->findOrFail($args['projectId']);
        $template = Template::query()->findOrFail($args['templateId']);

        $this->labels->applyTemplate($project, $template, $args['mode'] === 'REPLACE');

        return $project;
    }
}
