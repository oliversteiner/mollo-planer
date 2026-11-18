<?php

namespace App\GraphQL\Mutations;

use App\Models\Template;

/**
 * Deletes an own template for good (no trash). Projects made from it keep
 * their copies and only lose the note where they came from. Who may is
 * checked by the policy (@can).
 */
class DeleteTemplate
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        Template::query()->findOrFail($args['id'])->delete();

        return true;
    }
}
