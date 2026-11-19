<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The rule for passwords (docs: Auftrag 22 O4): 12 characters, no rules about the make-up, at most 72 bytes,
 * not in a known leak, and a service that does not answer does not block anybody.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'correct-horse-battery-staple';

    private function check(string $password): bool
    {
        return ! Validator::make(['password' => $password], ['password' => PasswordPolicy::rules()])->fails();
    }

    public function test_eleven_characters_are_refused_and_twelve_are_accepted_without_rules_about_the_make_up(): void
    {
        $this->assertFalse($this->check('abcdefghijk'));
        $this->assertTrue($this->check('abcdefghijkl'), 'no digit, capital or symbol is required');
        $this->assertTrue($this->check('only lowercase words are fine'));
    }

    public function test_a_password_of_up_to_72_bytes_is_accepted_and_a_longer_one_is_refused_with_a_clear_message(): void
    {
        $this->assertTrue($this->check(str_repeat('a', 72)));
        $this->assertFalse($this->check(str_repeat('a', 73)));
        $this->assertFalse($this->check(str_repeat('ä', 40)), '40 characters are 80 bytes: bcrypt would cut them off');

        $errors = Validator::make(['password' => str_repeat('a', 73)], ['password' => PasswordPolicy::rules()])->errors()->first('password');
        $this->assertSame(__('validation.password_too_long', ['max' => 72]), $errors);
    }

    public function test_a_known_leaked_password_is_refused(): void
    {
        config(['auth.password_uncompromised' => true]);
        $hash = strtoupper(sha1('password1234'));
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).':48000', 200)]);

        $this->assertFalse($this->check('password1234'));
        $this->assertTrue($this->check(self::GOOD), 'a password that is not in the answer passes');
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/range/'.substr($hash, 0, 5)));
    }

    public function test_a_service_that_is_down_does_not_block(): void
    {
        config(['auth.password_uncompromised' => true]);

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertTrue($this->check(self::GOOD));

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 503)]);
        $this->assertTrue($this->check(self::GOOD));
    }

    public function test_the_check_waits_three_seconds_at_most(): void
    {
        $verifier = app(UncompromisedVerifier::class);

        $this->assertInstanceOf(NotPwnedVerifier::class, $verifier);
        $this->assertSame(3, (new \ReflectionProperty(NotPwnedVerifier::class, 'timeout'))->getValue($verifier));
    }

    public function test_the_check_can_be_switched_off(): void
    {
        config(['auth.password_uncompromised' => false]);
        Http::fake();

        $this->assertTrue($this->check('password1234'));
        Http::assertNothingSent();
    }

    public function test_registration_uses_the_rule(): void
    {
        $this->postJson('/register', ['name' => 'Eva Neu', 'email' => 'eva@example.com', 'password' => 'abcdefghijk', 'password_confirmation' => 'abcdefghijk'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/register', ['name' => 'Eva Neu', 'email' => 'eva@example.com', 'password' => str_repeat('ä', 40), 'password_confirmation' => str_repeat('ä', 40)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_the_reset_uses_the_rule(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'abcdefghijk', 'password_confirmation' => 'abcdefghijk'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => self::GOOD, 'password_confirmation' => self::GOOD])
            ->assertOk();
    }

    public function test_the_invitation_uses_the_rule(): void
    {
        $user = User::factory()->create();
        $token = Password::broker('invitations')->createToken($user);

        $this->postJson('/invitation', ['token' => $token, 'email' => $user->email, 'password' => 'abcdefghijk', 'password_confirmation' => 'abcdefghijk'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_the_change_in_the_profile_uses_the_rule(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        try {
            app(UpdateUserPassword::class)->update($user, ['current_password' => 'old-password-123', 'password' => 'abcdefghijk', 'password_confirmation' => 'abcdefghijk']);
            $this->fail('A short password was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
    }

    public function test_the_artisan_commands_use_the_rule(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com', 'name' => 'Anna Keller']);

        $this->artisan('app:user-password', ['user' => 'anna@example.com'])
            ->expectsConfirmation(__('console.user_password.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), 'yes')
            ->expectsQuestion(__('console.user_password.password'), 'abcdefghijk')
            ->assertFailed();

        $this->artisan('app:create-admin', ['name' => 'Ada Admin', 'email' => 'ada@example.com', '--password' => true])
            ->expectsQuestion(__('console.create_admin.password'), 'abcdefghijk')
            ->assertFailed();

        $this->assertFalse(Hash::check('abcdefghijk', $user->fresh()->password));
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    }
}
