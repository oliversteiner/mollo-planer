<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Enums\UserActivity;
use App\Models\Role;
use App\Models\User;
use App\Services\UserInvitation;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user (admins only, checked by @can). The user gets an
 * invitation mail to set their own password, in the admin's current
 * language.
 */
class CreateUser
{
    public function __construct(private readonly UserInvitation $invitation) {}

    /**
     * @param  array{name: string, email: string, role?: string|null, activity?: string|null}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $role = isset($args['role'])
            ? Role::resolveOrFail(Role::SCOPE_SYSTEM, $args['role'])
            : Role::defaultOf(Role::SCOPE_SYSTEM);
        $activity = isset($args['activity']) ? UserActivity::from($args['activity']) : null;

        return DB::transaction(fn (): User => $this->invitation->invite(
            $args['name'],
            $args['email'],
            $role,
            Locale::tryFrom(app()->getLocale()) ?? Locale::DE,
            $activity,
        ));
    }
}
