<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Comment;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Comment.viewerCanEdit / viewerCanDelete, answered by the CommentPolicy.
 */
class CommentViewerCan
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function edit(Comment $comment, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('update', $comment);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function delete(Comment $comment, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('delete', $comment);
    }
}
