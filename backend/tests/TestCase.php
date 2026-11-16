<?php

namespace Tests;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Role;
use App\Support\Rights;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** The role of a shipped project key, for calls of Rights. */
    protected function projectRole(?ProjectRole $role): ?Role
    {
        return Role::resolve(Role::SCOPE_PROJECT, $role);
    }

    /** The role of a shipped system key, for calls of Rights. */
    protected function systemRole(?UserRole $role): ?Role
    {
        return Role::resolve(Role::SCOPE_SYSTEM, $role);
    }

    protected function tearDown(): void
    {
        Rights::clearCache();

        parent::tearDown();
    }
}
