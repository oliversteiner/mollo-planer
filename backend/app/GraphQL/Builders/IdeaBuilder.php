<?php

namespace App\GraphQL\Builders;

use App\Enums\Right;
use App\Models\Idea;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.ideas (docs/auftrag-m6.md §2.3): the ideas of the viewer, newest first. With ideas.viewAny all ideas,
 * or those of the author given with authorId (only with that right). Other people's ideas are for reading.
 */
class IdeaBuilder
{
    /**
     * @param  array{status?: string|null, projectId?: int|string|null, authorId?: int|string|null}  $args
     * @return Builder<Idea>
     *
     * @throws AuthorizationException
     */
    public function visible(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder
    {
        $viewer = $context->user();
        $all = Rights::allows($viewer, Right::IDEAS_VIEW_ANY);
        $authorId = $args['authorId'] ?? null;

        if ($authorId !== null && ! $all && (int) $authorId !== (int) $viewer->getKey()) {
            throw new AuthorizationException;
        }

        return Idea::query()
            ->when(! $all, fn (Builder $query) => $query->where('user_id', $viewer->getKey()))
            ->when($authorId !== null, fn (Builder $query) => $query->where('user_id', $authorId))
            ->when(isset($args['status']), fn (Builder $query) => $query->where('status', $args['status']))
            ->when(isset($args['projectId']), fn (Builder $query) => $query->where('project_id', $args['projectId']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
