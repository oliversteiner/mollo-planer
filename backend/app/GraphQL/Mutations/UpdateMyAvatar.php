<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use App\Services\UserAvatar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Sets the viewer's own avatar (checked by UpdateMyAvatarValidator).
 */
class UpdateMyAvatar
{
    use ResolvesViewer;

    public function __construct(private readonly UserAvatar $avatar) {}

    /**
     * @param  array{avatar: UploadedFile}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): User
    {
        $viewer = $this->viewer($context);

        return DB::transaction(fn (): User => $this->avatar->replace($viewer, $args['avatar']));
    }
}
