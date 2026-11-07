<?php

namespace App\GraphQL\Builders;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Argument builders for the todo list (Query.todos).
 */
class TodoBuilder
{
    /**
     * Todos of all milestones in the given project. Visibility is checked
     * separately by the visibleToViewer scope on the same query.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    public function inProject(Builder $builder, int|string $projectId): Builder
    {
        return $builder->whereIn(
            'milestone_id',
            Milestone::query()->select('id')->where('project_id', $projectId),
        );
    }
}
