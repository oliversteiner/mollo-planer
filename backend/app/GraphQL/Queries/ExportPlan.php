<?php

namespace App\GraphQL\Queries;

use App\Models\Project;
use App\Planer\Export;
use GraphQL\Error\Error;
use RuntimeException;

/**
 * Query.exportPlan: the canonical text of the planer file. The right
 * (planer.export) is checked by @can on the field.
 */
class ExportPlan
{
    public function __construct(private readonly Export $export) {}

    /**
     * @param  array{projectId: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): string
    {
        $project = Project::query()->findOrFail($args['projectId']);

        try {
            return $this->export->text($project);
        } catch (RuntimeException $error) {
            throw new Error($error->getMessage());
        }
    }
}
