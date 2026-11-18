<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SecurityChange;
use App\Services\UserSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What happens after a password reset, and the notice mail for every change of the sign-in
 * (docs: Auftrag 22 O5).
 */
class SecurityChangeMailTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'correct-horse-battery-staple';

    private function user(): User
    {
        return User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', 'remember_token' => 'lost-device']);
    }

    private function reset(User $user): TestResponse
    {
        $token = Password::broker()->createToken($user);

        return $this->postJson('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);
    }

    public function test_after_a_reset_other_sessions_remembered_logins_and_tokens_are_gone_and_nobody_is_logged_in(): void
    {
        // Tests use the array session driver; production uses the database (only there sessions can be ended).
        config(['session.driver' => 'database']);
        Notification::fake();
        $user = $this->user();
        $other = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $user->createToken('laptop', ['mcp']);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

        $this->reset($user)->assertOk();

        $user->refresh();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count(), 'sessions of other people stay');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('lost-device', $user->remember_token);
        $this->assertNotNull($user->two_factor_secret, 'the second factor stays');
        $this->assertGuest();
    }

    public function test_the_reset_sends_one_notice_mail_in_the_language_of_the_request(): void
    {
        Notification::fake();
        $user = $this->user();
        app()->setLocale('en');

        $this->reset($user)->assertOk();

        Notification::assertSentToTimes($user, SecurityChange::class, 1);
        Notification::assertSentTo($user, SecurityChange::class, function (SecurityChange $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $notification->kind === SecurityChange::PASSWORD
                && $notification->locale === 'en'
                && $mail->subject === 'Your password was changed'
                && ! str_contains(json_encode($mail->toArray()), self::NEW_PASSWORD)
                && $mail->actionUrl === null
                && in_array('If this was not you, contact an admin.', $mail->introLines, true);
        });
    }

    /**
     * @return array<string, array{0: \Closure(User): object, 1: string}>
     */
    public static function events(): array
    {
        return [
            'password changed in the profile' => [fn (User $user) => new PasswordUpdatedViaController($user), SecurityChange::PASSWORD],
            'authenticator set up' => [fn (User $user) => new TwoFactorAuthenticationConfirmed($user), SecurityChange::AUTHENTICATOR_ADDED],
            'authenticator removed' => [fn (User $user) => new TwoFactorAuthenticationDisabled($user), SecurityChange::AUTHENTICATOR_REMOVED],
            'passkey added' => [fn (User $user) => new PasskeyRegistered($user, $user->passkeys()->forceCreate(['name' => 'Laptop', 'credential_id' => 'c1', 'credential' => []])), SecurityChange::PASSKEY_ADDED],
            'passkey removed' => [fn (User $user) => new PasskeyDeleted($user, $user->passkeys()->forceCreate(['name' => 'Laptop', 'credential_id' => 'c2', 'credential' => []])), SecurityChange::PASSKEY_REMOVED],
            'recovery codes made' => [fn (User $user) => new RecoveryCodesGenerated($user), SecurityChange::RECOVERY_CODES],
        ];
    }

    /**
     * @param  \Closure(User): object  $event
     */
    #[DataProvider('events')]
    public function test_every_change_sends_one_notice_mail(\Closure $event, string $kind): void
    {
        Notification::fake();
        $user = $this->user();

        event($event($user));

        Notification::assertSentToTimes($user, SecurityChange::class, 1);
        Notification::assertSentTo($user, SecurityChange::class, fn (SecurityChange $notification): bool => $notification->kind === $kind);
    }

    public function test_the_start_of_the_setup_and_the_codes_that_come_with_it_send_nothing(): void
    {
        Notification::fake();
        $user = $this->user();

        event(new TwoFactorAuthenticationEnabled($user));

        Notification::assertNothingSent();
    }

    public function test_a_confirmed_setup_with_its_codes_is_one_mail_not_two(): void
    {
        Notification::fake();
        $user = $this->user();

        event(new TwoFactorAuthenticationEnabled($user));
        event(new TwoFactorAuthenticationConfirmed($user));

        Notification::assertSentToTimes($user, SecurityChange::class, 1);
    }

    public function test_the_admin_reset_changes_the_factors_directly_and_sends_no_event_mail_here(): void
    {
        Notification::fake();
        $user = $this->user();
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        app(UserSecurity::class)->resetTwoFactor($user);

        Notification::assertNotSentTo($user, SecurityChange::class);
    }

    public function test_the_first_password_of_an_invitation_is_not_a_change(): void
    {
        Notification::fake();
        $user = $this->user();
        $token = Password::broker('invitations')->createToken($user);

        $this->postJson('/invitation', ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertOk();

        Notification::assertNotSentTo($user, SecurityChange::class);
    }

    public function test_the_mails_exist_in_both_languages(): void
    {
        $user = $this->user();

        foreach (['de', 'en'] as $locale) {
            foreach ([SecurityChange::PASSWORD, SecurityChange::AUTHENTICATOR_ADDED, SecurityChange::AUTHENTICATOR_REMOVED, SecurityChange::PASSKEY_ADDED, SecurityChange::PASSKEY_REMOVED, SecurityChange::RECOVERY_CODES] as $kind) {
                app()->setLocale($locale);
                $mail = (new SecurityChange($kind))->locale($locale)->toMail($user);

                $this->assertNotSame("mail.security_change.$kind.subject", $mail->subject);
                $this->assertStringNotContainsString('mail.security_change', implode(' ', [...$mail->introLines, ...$mail->outroLines]));
            }
        }
    }
}
