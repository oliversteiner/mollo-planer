<?php

namespace App\GraphQL\Queries;

use App\Enums\ProjectAbility;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use App\Support\ProjectPermissions;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Names of people, to pick project members. Shows no e-mail address and no
 * security status. Only for viewers who may manage members in some project
 * (or who may manage users). Others get an empty list.
 */
class UserSearch
{
    use ResolvesViewer;

    private const LIMIT = 20;

    /**
     * @param  array{term: string}  $args
     * @return list<User>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);

        if (! ProjectPermissions::allowsInAnyProject($viewer, ProjectAbility::MEMBERS_MANAGE)) {
            return [];
        }

        $term = trim($args['term']);

        if ($term === '') {
            return [];
        }

        return User::query()
            ->select(['id', 'name'])
            ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->all();
    }
}
