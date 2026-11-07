<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Comment;
use App\Models\Task;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Adds a comment to a task. The author is the viewer, never taken from input.
 */
class AddComment
{
    use ResolvesViewer;

    /**
     * @param  array{task_id: int|string, body: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Comment
    {
        $comment = new Comment(['body' => $args['body']]);
        $comment->task()->associate(Task::findOrFail($args['task_id']));
        $comment->author()->associate($this->viewer($context));
        $comment->save();

        return $comment;
    }
}
