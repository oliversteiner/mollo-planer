<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Turns e-mail notifications on or off for the viewer only.
 */
class UpdateMyNotifications
{
    use ResolvesViewer;

    /**
     * @param  array{emailEnabled: bool}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): User
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): User {
            $viewer->notify_by_email = $args['emailEnabled'];
            $viewer->save();

            return $viewer;
        });
    }
}
