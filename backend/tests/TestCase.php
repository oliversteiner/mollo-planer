<?php

namespace Tests;

use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\Role;
use App\Support\Rights;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

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

    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches the real leak check (or anything else on the network): a test that needs
        // it fakes the answer with Http::fake().
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Rights::clearCache();

        parent::tearDown();
    }
}
