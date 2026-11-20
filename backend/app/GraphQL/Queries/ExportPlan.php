<?php

namespace App\GraphQL\Queries;

use App\Models\Project;
use App\Planer\Export;
use App\Planer\ExportFilter;
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
     * @param  array{projectId: int|string, version?: string|null, milestoneIds?: list<int|string>|null, taskStatus?: list<string>|null, includeReleased?: bool|null}  $args
     */
    public function __invoke(mixed $root, array $args): string
    {
        $project = Project::query()->findOrFail($args['projectId']);

        try {
            return $this->export->text($project, new ExportFilter(
                version: $args['version'] ?? null,
                milestoneIds: array_map('intval', array_map(fn ($id): string => ltrim((string) $id, 'm-'), $args['milestoneIds'] ?? [])),
                taskStatus: $args['taskStatus'] ?? [],
                includeReleased: $args['includeReleased'] ?? true,
            ));
        } catch (RuntimeException $error) {
            throw new Error($error->getMessage());
        }
    }
}
