<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
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
     * @param  array{name: string, email: string, is_admin?: bool}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return DB::transaction(fn (): User => $this->invitation->invite(
            $args['name'],
            $args['email'],
            $args['is_admin'] ?? false,
            Locale::tryFrom(app()->getLocale()) ?? Locale::DE,
        ));
    }
}
