<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureStrongAuthentication;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Login and locks while an admin has switched two-factor authentication
 * off (App\Support\AppSettings), and in production, where the switch has
 * no effect. The GraphQL side (who may switch) is in AppSettingsTest.
 */
class TwoFactorSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_switched_off_the_password_is_enough_even_with_an_authenticator_app(): void
    {
        $this->switchOff();
        [$user] = $this->userWithTwoFactor();

        $this->login($user)->assertOk()->assertJsonPath('two_factor', false);

        $this->me()->assertOk()->assertJsonPath('data.me.id', (string) $user->id);
    }

    public function test_switched_off_a_user_without_factor_is_not_sent_to_setup(): void
    {
        $this->switchOff();
        $user = $this->user();

        $this->login($user)->assertOk();

        $this->me()->assertOk()->assertJsonPath('data.me.id', (string) $user->id);
    }

    public function test_switched_off_account_routes_need_no_strong_session(): void
    {
        $this->switchOff();
        [$user] = $this->userWithTwoFactor();
        $this->login($user);

        $this->putJson('/user/password', ['current_password' => self::PASSWORD, 'password' => 'Other-Pass-1', 'password_confirmation' => 'Other-Pass-1'])
            ->assertOk();
    }

    public function test_switching_back_on_locks_sessions_that_used_only_the_password(): void
    {
        $this->switchOff();
        [$user, $secret] = $this->userWithTwoFactor();
        $this->login($user);
        AppSettings::setTwoFactorEnabled(true, $user);

        $this->me()->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);

        $this->postJson('/logout');
        $this->login($user)->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertNoContent();
        $this->me()->assertOk();
    }

    public function test_in_production_the_login_still_asks_for_the_code_and_the_lock_holds(): void
    {
        AppSettings::setTwoFactorEnabled(false, $this->user(['role' => 'admin']));
        $this->app['env'] = 'production';
        // Outside "testing" Laravel checks the CSRF token again.
        $this->withSession(['_token' => 'csrf-token'])->withHeader('X-CSRF-TOKEN', 'csrf-token');
        [$user] = $this->userWithTwoFactor();

        $this->login($user)->assertOk()->assertJsonPath('two_factor', true);
        $this->actingAs($user)->postJson('/graphql', ['query' => '{ me { id } }'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);
    }

    private function switchOff(): void
    {
        AppSettings::setTwoFactorEnabled(false, $this->user(['role' => 'admin']));
    }

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
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    private function login(User $user): TestResponse
    {
        return $this->postJson('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
    }

    private function me(): TestResponse
    {
        return $this->postJson('/graphql', ['query' => '{ me { id } }']);
    }
}
