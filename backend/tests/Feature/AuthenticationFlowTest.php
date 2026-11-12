<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Http\Middleware\EnsureStrongAuthentication;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserInvited;
use App\Services\UserInvitation;
use App\Support\StrongAuthentication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as MailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkey;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The login flows through the real Fortify routes, followed by a GraphQL
 * request as the frontend would send it (Origin of the SPA).
 */
class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://localhost:3000';

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', self::FRONTEND);
    }

    // ─── Routes ──────────────────────────────────────────────────

    public function test_rest_user_route_is_gone(): void
    {
        $this->getJson('/api/user')->assertNotFound();
    }

    // ─── Password without factor ─────────────────────────────────

    public function test_password_login_without_factor_leads_to_setup(): void
    {
        $user = $this->user();

        $this->login($user)->assertOk()->assertJsonPath('two_factor', false);

        $this->me()->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_SETUP_REQUIRED);
    }

    public function test_confirming_the_authenticator_app_unlocks_the_session(): void
    {
        $user = $this->user();
        $this->login($user);

        // Fortify fires this after POST /user/confirmed-two-factor-authentication.
        event(new TwoFactorAuthenticationConfirmed($user));

        $this->me()->assertOk()->assertJsonPath('data.me.id', (string) $user->id);
    }

    // ─── Password plus 2FA ───────────────────────────────────────

    public function test_password_and_valid_code_give_a_strong_session(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();

        $this->login($user)->assertOk()->assertJsonPath('two_factor', true);
        $this->me()->assertJsonPath('errors.0.message', 'Unauthenticated.');

        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertNoContent();

        $this->me()->assertOk()->assertJsonPath('data.me.id', (string) $user->id);
    }

    public function test_invalid_code_gives_no_session(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->login($user);

        $this->postJson('/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();

        $this->assertGuest();
        $this->assertNull(session(StrongAuthentication::SESSION_KEY));
    }

    public function test_recovery_code_gives_a_strong_session(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->login($user);

        $this->postJson('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertNoContent();

        $this->me()->assertOk();
    }

    public function test_logout_ends_the_strong_session(): void
    {
        [$user, $secret] = $this->userWithTwoFactor();
        $this->login($user);
        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)]);

        $this->postJson('/logout')->assertNoContent();

        $this->assertNull(session(StrongAuthentication::SESSION_KEY));
        $this->me()->assertJsonPath('errors.0.message', 'Unauthenticated.');
    }

    public function test_a_session_without_second_factor_may_log_in_again(): void
    {
        // For example a session from before the admin switched 2FA on.
        [$user, $secret] = $this->userWithTwoFactor();
        $this->actingAs($user);
        $this->me()->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);

        $this->login($user)->assertOk()->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertNoContent();

        $this->me()->assertOk()->assertJsonPath('data.me.id', (string) $user->id);
    }

    public function test_a_logged_in_session_gets_json_instead_of_a_redirect(): void
    {
        [$user] = $this->userWithTwoFactor();
        $this->actingAs($user)->withSession([StrongAuthentication::SESSION_KEY => (string) $user->id]);

        $this->login($user)->assertOk()
            ->assertJsonPath('message', 'Already authenticated.')
            ->assertJsonPath('two_factor', false);
        $this->getJson('/passkeys/login/options')->assertOk()->assertJsonPath('message', 'Already authenticated.');
    }

    // ─── Passkeys ────────────────────────────────────────────────

    public function test_verified_passkey_gives_a_strong_session(): void
    {
        $user = $this->user();
        $passkey = $this->passkeyFor($user);

        // The passkey login verifies (with user verification) and then logs in.
        event(new PasskeyVerified($user, $passkey));
        $this->actingAs($user);

        $this->me()->assertOk();
    }

    public function test_passkeys_require_user_verification_and_the_frontend_origin(): void
    {
        $this->assertSame([self::FRONTEND], config('passkeys.allowed_origins'));
        $this->assertSame('localhost', config('passkeys.relying_party_id'));

        $options = file_get_contents(base_path('vendor/laravel/passkeys/src/Actions/GenerateVerificationOptions.php'));
        $this->assertStringContainsString('USER_VERIFICATION_REQUIREMENT_REQUIRED', $options);
    }

    public function test_removing_the_last_factor_locks_the_session_again(): void
    {
        $user = $this->user(['two_factor_confirmed_at' => now()]);
        $this->actingAs($user);
        event(new TwoFactorAuthenticationConfirmed($user));
        $this->me()->assertOk();

        $user->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save();
        event(new TwoFactorAuthenticationDisabled($user));

        $this->me()->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_SETUP_REQUIRED);
    }

    public function test_removing_one_factor_keeps_the_session_when_another_exists(): void
    {
        $user = $this->user(['two_factor_confirmed_at' => now()]);
        $this->passkeyFor($user);
        $this->actingAs($user);
        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        $user->forceFill(['two_factor_confirmed_at' => null])->save();
        event(new TwoFactorAuthenticationDisabled($user));

        $this->me()->assertOk();
    }

    // ─── Password alone must not add or change factors ───────────

    public function test_password_alone_cannot_take_over_a_passkey_account(): void
    {
        $user = $this->user();
        $this->passkeyFor($user);

        // No authenticator app, so Fortify logs in without a code challenge.
        $this->login($user)->assertOk()->assertJsonPath('two_factor', false);
        $this->postJson('/user/confirm-password', ['password' => self::PASSWORD])->assertCreated();

        $this->me()->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);

        $blocked = [
            ['post', '/user/two-factor-authentication', []],
            ['get', '/user/passkeys/options', []],
            ['put', '/user/password', ['current_password' => self::PASSWORD, 'password' => 'Other-Pass-1', 'password_confirmation' => 'Other-Pass-1']],
            ['put', '/user/profile-information', ['name' => 'Mallory', 'email' => 'mallory@example.com']],
        ];

        foreach ($blocked as [$method, $uri, $data]) {
            $this->json($method, $uri, $data)->assertForbidden()
                ->assertJsonPath('code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);
        }

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_first_setup_is_allowed_with_the_password(): void
    {
        $user = $this->user();
        $this->login($user);
        $this->postJson('/user/confirm-password', ['password' => self::PASSWORD]);

        $this->postJson('/user/two-factor-authentication')->assertOk();
        $this->getJson('/user/two-factor-secret-key')->assertOk();

        $this->putJson('/user/password', ['current_password' => self::PASSWORD, 'password' => 'Other-Pass-1', 'password_confirmation' => 'Other-Pass-1'])
            ->assertForbidden();
    }

    public function test_adding_a_further_factor_does_not_mark_the_session(): void
    {
        $user = $this->user(['two_factor_confirmed_at' => now()]);
        $this->actingAs($user);

        $passkey = $this->passkeyFor($user);
        event(new PasskeyRegistered($user, $passkey));
        $this->assertNull(session(StrongAuthentication::SESSION_KEY));

        $other = $this->user();
        $this->passkeyFor($other);
        $this->actingAs($other);
        event(new TwoFactorAuthenticationConfirmed($other));
        $this->assertNull(session(StrongAuthentication::SESSION_KEY));
    }

    // ─── Invitation ──────────────────────────────────────────────

    public function test_invited_user_sets_password_and_can_log_in(): void
    {
        Notification::fake();
        $user = $this->user();
        $token = $this->invite($user);

        $this->postJson('/invitation', [
            'token' => $token, 'email' => $user->email, 'password' => 'New-Password-1', 'password_confirmation' => 'New-Password-1',
        ])->assertOk();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'New-Password-1'])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invitation_link_is_single_use_and_expires_after_seven_days(): void
    {
        Notification::fake();
        $user = $this->user();
        $payload = fn (string $token) => [
            'token' => $token, 'email' => $user->email, 'password' => 'New-Password-1', 'password_confirmation' => 'New-Password-1',
        ];

        $token = $this->invite($user);
        $this->postJson('/invitation', $payload('wrong'))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/invitation', $payload($token))->assertOk();
        $this->postJson('/invitation', $payload($token))->assertUnprocessable();

        $token = $this->invite($user);
        $this->travel(6)->days();
        $this->assertTrue(DB::table('password_reset_tokens')->where('email', $user->email)->exists());
        $this->travel(2)->days();
        $this->postJson('/invitation', $payload($token))->assertUnprocessable();
    }

    public function test_create_user_sends_a_queued_invitation_in_the_admins_language(): void
    {
        Notification::fake();
        $admin = $this->user(['role' => 'admin']);
        $this->actingAs($admin)->withSession([StrongAuthentication::SESSION_KEY => (string) $admin->id]);

        $this->withHeader('Accept-Language', 'de-CH,de;q=0.9')
            ->postJson('/api', ['query' => 'mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { id } }'])
            ->assertOk()->assertJsonMissingPath('errors');

        $invited = User::where('email', 'fritz@example.com')->firstOrFail();
        Notification::assertSentTo($invited, UserInvited::class, function (UserInvited $notification) use ($invited) {
            $mail = $notification->toMail($invited);

            return $notification instanceof ShouldQueue
                && $notification->afterCommit === true
                && $notification->locale === 'de'
                && str_starts_with($mail->actionUrl, self::FRONTEND.'/invitation?token=');
        });
    }

    // ─── Forgot password ─────────────────────────────────────────

    public function test_forgot_password_sends_a_queued_mail_with_a_frontend_link(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            return $notification instanceof ShouldQueue
                && str_starts_with($notification->toMail($user)->actionUrl, self::FRONTEND.'/reset-password?token=');
        });
    }

    /**
     * There is no "resend invitation": after the 7 days the invited person
     * sets the password with "forgot password".
     */
    public function test_after_an_expired_invitation_forgot_password_sets_the_password(): void
    {
        Notification::fake();
        $user = app(UserInvitation::class)->invite('Fritz', 'fritz@example.com', UserRole::MITGLIED, Locale::DE);
        $this->travel(8)->days();

        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertOk();

        $this->login($user)->assertOk()->assertJsonPath('two_factor', false);
    }

    /**
     * @param  \Closure(): MailNotification  $notification
     */
    #[DataProvider('accountMails')]
    public function test_html_in_the_name_is_escaped_in_account_mails(\Closure $notification): void
    {
        $user = $this->user(['name' => 'Fritz <script>alert("x")</script>']);

        $html = (string) $notification()->toMail($user)->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return array<string, array{\Closure(): MailNotification}>
     */
    public static function accountMails(): array
    {
        return [
            'invitation' => [fn () => new UserInvited('token')],
            'password reset' => [fn () => new ResetPasswordNotification('token')],
        ];
    }

    // ─── Language, CORS, limits ──────────────────────────────────

    public function test_messages_follow_accept_language(): void
    {
        $user = $this->user();

        $this->withHeader('Accept-Language', 'de-CH')
            ->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertJsonPath('errors.email.0', 'Diese Zugangsdaten stimmen nicht.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_cors_allows_only_the_frontend_with_credentials(): void
    {
        $preflight = fn (string $origin) => $this->withHeaders([
            'Origin' => $origin, 'Access-Control-Request-Method' => 'POST',
        ])->options('/api');

        $preflight(self::FRONTEND)
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        // A foreign origin never gets itself echoed back, so the browser blocks it.
        $this->assertNotSame(
            'https://evil.example',
            $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_graphql_is_rate_limited(): void
    {
        $this->assertContains('throttle:graphql', config('lighthouse.route.middleware'));
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes = []): User
    {
        return User::factory()->create(['password' => self::PASSWORD, ...$attributes]);
    }

    /**
     * @return array{User, string}
     */
    private function userWithTwoFactor(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = $this->user();
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one', 'recovery-two'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    private function passkeyFor(User $user): Passkey
    {
        return $user->passkeys()->forceCreate([
            'user_id' => $user->id, 'name' => 'Laptop', 'credential_id' => 'cred-'.$user->id, 'credential' => [],
        ]);
    }

    private function login(User $user): TestResponse
    {
        return $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    private function me(): TestResponse
    {
        return $this->postJson('/api', ['query' => '{ me { id } }']);
    }

    private function invite(User $user): string
    {
        return Password::broker('invitations')->createToken($user);
    }
}
