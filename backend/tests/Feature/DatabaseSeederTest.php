<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_creates_no_user(): void
    {
        $this->artisan('db:seed', ['--no-interaction' => true])
            ->expectsOutputToContain('app:create-admin')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
    }
}
