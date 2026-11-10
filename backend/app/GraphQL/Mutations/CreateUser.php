<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Enums\UserFunction;
use App\Enums\UserRole;
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
     * @param  array{name: string, email: string, role?: string|null, job_function?: string|null}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $role = UserRole::from($args['role'] ?? UserRole::MITGLIED->value);
        $jobFunction = isset($args['job_function']) ? UserFunction::from($args['job_function']) : null;

        return DB::transaction(fn (): User => $this->invitation->invite(
            $args['name'],
            $args['email'],
            $role,
            Locale::tryFrom(app()->getLocale()) ?? Locale::DE,
            $jobFunction,
        ));
    }
}
