<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * The login counts as password confirmation (docs: Auftrag 22 O6).
 */
class PasswordConfirmationOnLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery-staple';

    private function user(bool $authenticator = false): array
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);
        $secret = null;

        if ($authenticator) {
            $secret = app(Google2FA::class)->generateSecretKey();
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one', 'recovery-two'])),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return [$user, $secret];
    }

    private function confirmed(): bool
    {
        return (bool) $this->getJson('/user/confirmed-password-status')->assertOk()->json('confirmed');
    }

    public function test_after_the_login_with_the_password_the_setup_needs_no_second_entry(): void
    {
        [$user] = $this->user();

        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();

        $this->assertTrue($this->confirmed());
    }

    public function test_with_an_authenticator_it_counts_only_after_the_code_not_after_the_password_step(): void
    {
        [$user, $secret] = $this->user(true);

        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk()->assertJsonPath('two_factor', true);
        $this->assertNull(session('auth.password_confirmed_at'), 'the login is not complete yet');
        $this->getJson('/user/confirmed-password-status')->assertUnauthorized();

        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertNoContent();
        $this->assertTrue($this->confirmed());
    }

    public function test_a_recovery_code_ends_the_login_the_same_way(): void
    {
        [$user] = $this->user(true);

        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->postJson('/two-factor-challenge', ['recovery_code' => 'recovery-one'])->assertNoContent();

        $this->assertTrue($this->confirmed());
    }

    public function test_a_wrong_password_confirms_nothing(): void
    {
        [$user] = $this->user();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password-123'])->assertUnprocessable();

        $this->assertNull(session('auth.password_confirmed_at'));
        $this->getJson('/user/confirmed-password-status')->assertUnauthorized();
    }

    public function test_after_the_window_the_password_is_asked_for_again(): void
    {
        [$user] = $this->user();
        $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertOk();
        $this->assertTrue($this->confirmed());

        $this->travel(config('auth.password_timeout') + 60)->seconds();

        $this->assertFalse($this->confirmed());
    }

    public function test_a_passkey_login_types_no_password_and_does_not_count(): void
    {
        [$user] = $this->user();

        // The passkey login logs in on its own route; the Login event of it must not set the time.
        $this->withSession([])->postJson('/passkeys/login', [])->assertStatus(422);
        $this->actingAs($user);
        event(new Login('web', $user, false));

        $this->assertFalse($this->confirmed());
    }
}
