<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Services\UserAvatar;
use Illuminate\Support\Facades\DB;

/**
 * Removes an avatar: one's own, or as an admin someone else's (@can).
 */
class DeleteUserAvatar
{
    public function __construct(private readonly UserAvatar $avatar) {}

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return DB::transaction(fn (): User => $this->avatar->remove(User::findOrFail($args['id'])));
    }
}
