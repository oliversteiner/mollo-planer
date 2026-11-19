<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserRoleApproveCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', ...$attributes]);
    }

    public function test_role_sets_the_system_role_and_names_the_old_and_the_new_one(): void
    {
        Log::spy();
        $user = $this->user(['role' => 'mitglied']);

        $this->artisan('app:user-role', ['user' => 'anna@example.com', 'role' => 'admin'])
            ->expectsConfirmation(__('console.user_role.confirm', ['name' => 'Anna Keller', 'role' => 'admin']), 'yes')
            ->expectsOutputToContain('mitglied → admin')
            ->assertSuccessful();

        $this->assertSame('admin', $user->fresh()->role->key);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_role_without_a_value_asks_for_a_choice(): void
    {
        $user = $this->user(['role' => 'mitglied']);

        $this->artisan('app:user-role', ['user' => (string) $user->id])
            ->expectsChoice(__('console.user_role.choose', ['name' => 'Anna Keller', 'role' => 'mitglied']), 'lesend', collect(Role::ofScope(Role::SCOPE_SYSTEM))->pluck('key')->all())
            ->expectsConfirmation(__('console.user_role.confirm', ['name' => 'Anna Keller', 'role' => 'lesend']), 'yes')
            ->assertSuccessful();

        $this->assertSame('lesend', $user->fresh()->role->key);
    }

    public function test_role_refuses_an_unknown_role_and_writes_nothing_without_a_terminal(): void
    {
        $user = $this->user(['role' => 'mitglied']);

        $this->artisan('app:user-role', ['user' => 'anna@example.com', 'role' => 'gibtsnicht'])->assertFailed();
        $this->artisan('app:user-role', ['user' => 'anna@example.com', 'role' => 'admin', '--no-interaction' => true])->assertFailed();
        $this->artisan('app:user-role', ['user' => 'anna@example.com', '--no-interaction' => true])->assertFailed();

        $this->assertSame('mitglied', $user->fresh()->role->key);
    }

    public function test_the_last_admin_keeps_the_admin_role(): void
    {
        $admin = $this->user(['role' => 'admin', 'approved_at' => now()]);

        $this->artisan('app:user-role', ['user' => 'anna@example.com', 'role' => 'mitglied'])->assertFailed();

        $this->assertSame('admin', $admin->fresh()->role->key);
    }

    public function test_an_admin_may_lose_the_role_when_another_admin_remains(): void
    {
        $admin = $this->user(['role' => 'admin', 'approved_at' => now()]);
        $this->user(['email' => 'zweite@example.com', 'role' => 'admin', 'approved_at' => now()]);

        $this->artisan('app:user-role', ['user' => 'anna@example.com', 'role' => 'mitglied'])
            ->expectsConfirmation(__('console.user_role.confirm', ['name' => 'Anna Keller', 'role' => 'mitglied']), 'yes')
            ->assertSuccessful();

        $this->assertSame('mitglied', $admin->fresh()->role->key);
    }

    public function test_approve_releases_a_waiting_account_and_sends_the_mail_once(): void
    {
        Notification::fake();
        $user = $this->user(['approved_at' => null, 'email_verified_at' => now()]);

        $this->artisan('app:user-approve', ['user' => 'anna@example.com'])
            ->expectsConfirmation(__('console.user_approve.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), 'yes')
            ->assertSuccessful();

        $this->assertNotNull($user->fresh()->approved_at);
        Notification::assertSentToTimes($user, AccountApproved::class, 1);

        $this->artisan('app:user-approve', ['user' => 'anna@example.com', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user_approve.already', ['name' => 'Anna Keller']))
            ->assertSuccessful();

        Notification::assertSentToTimes($user, AccountApproved::class, 1);
    }

    public function test_approve_warns_about_an_unconfirmed_address_and_writes_nothing_when_declined(): void
    {
        Notification::fake();
        $user = $this->user(['approved_at' => null, 'email_verified_at' => null]);

        $this->artisan('app:user-approve', ['user' => 'anna@example.com'])
            ->expectsConfirmation(__('console.user_approve.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), 'no')
            ->assertFailed();

        $this->assertNull($user->fresh()->approved_at);

        $this->artisan('app:user-approve', ['user' => 'anna@example.com'])
            ->expectsConfirmation(__('console.user_approve.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), 'yes')
            ->expectsOutputToContain(__('console.user_approve.unverified'))
            ->assertSuccessful();
    }
}
