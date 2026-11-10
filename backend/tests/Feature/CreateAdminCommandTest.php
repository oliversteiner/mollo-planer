<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'anna@example.com';

    private const PASSWORD = 'correct-horse-battery-staple';

    public function test_creates_an_admin_and_sends_an_invitation_in_the_app_language(): void
    {
        Notification::fake();
        config(['app.locale' => 'de']);

        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL])
            ->assertSuccessful();

        $admin = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertSame('Anna Admin', $admin->name);
        Notification::assertSentTo($admin, UserInvited::class, fn (UserInvited $notification) => $notification->locale === 'de'
            && Password::broker('invitations')->tokenExists($admin, $notification->token));
    }

    public function test_invitation_language_can_be_chosen(): void
    {
        Notification::fake();

        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL, '--locale' => 'en'])
            ->assertSuccessful();

        Notification::assertSentTo(User::where('email', self::EMAIL)->firstOrFail(), UserInvited::class, fn (UserInvited $notification) => $notification->locale === 'en');
    }

    public function test_asks_for_missing_name_and_email(): void
    {
        Notification::fake();

        $this->artisan('app:create-admin')
            ->expectsQuestion(__('console.create_admin.name'), 'Anna Admin')
            ->expectsQuestion(__('console.create_admin.email'), self::EMAIL)
            ->assertSuccessful();

        $this->assertSame(UserRole::ADMIN, User::where('email', self::EMAIL)->firstOrFail()->role);
    }

    public function test_password_option_asks_for_the_password_and_sends_no_mail(): void
    {
        Notification::fake();

        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL, '--password' => true])
            ->expectsQuestion(__('console.create_admin.password'), self::PASSWORD)
            ->expectsQuestion(__('console.create_admin.password_confirmation'), self::PASSWORD)
            ->assertSuccessful();

        $admin = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        Notification::assertNothingSent();
    }

    public function test_password_option_without_a_terminal_fails(): void
    {
        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL, '--password' => true, '--no-interaction' => true])
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public function test_rejects_an_email_that_is_taken(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => self::EMAIL]);

        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL])
            ->assertFailed();

        $this->assertSame(UserRole::MITGLIED, $existing->fresh()->role);
        $this->assertSame(1, User::count());
        Notification::assertNothingSent();
    }

    public function test_rejects_invalid_input(): void
    {
        $this->artisan('app:create-admin', ['name' => 'A', 'email' => self::EMAIL])->assertFailed();
        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => 'not-an-email'])->assertFailed();
        $this->artisan('app:create-admin', ['name' => 'Anna Admin', 'email' => self::EMAIL, '--locale' => 'fr'])->assertFailed();

        $this->assertSame(0, User::count());
    }
}
