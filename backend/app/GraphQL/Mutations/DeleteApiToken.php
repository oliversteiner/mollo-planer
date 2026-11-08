<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Revokes one of the viewer's own tokens; someone else's is not found.
 */
class DeleteApiToken
{
    use ResolvesViewer;

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): ?PersonalAccessToken
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($viewer, $args): ?PersonalAccessToken {
            $token = $viewer->tokens()->whereKey($args['id'])->first();
            $token?->delete();

            return $token;
        });
    }
}
