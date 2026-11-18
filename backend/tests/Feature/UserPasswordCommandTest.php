<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class UserPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'correct-horse-battery-staple';

    private function user(): User
    {
        return User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', 'remember_token' => 'lost-device']);
    }

    private function change(string $user, string $answer = 'yes'): PendingCommand
    {
        return $this->artisan('app:user-password', ['user' => $user])
            ->expectsConfirmation(__('console.user_password.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), $answer);
    }

    public function test_changes_the_password_of_a_user_found_by_email_in_any_case(): void
    {
        $user = $this->user();
        $old = $user->password;

        $this->change('ANNA@example.com')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), self::NEW_PASSWORD)
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotSame($old, $user->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
    }

    public function test_a_wrong_confirmation_writes_nothing(): void
    {
        $user = $this->user();
        $old = $user->password;

        // The prompt refuses the answer (in a terminal it asks again); without a valid one the command stops.
        $this->change('anna@example.com')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), 'something-else-entirely')
            ->assertFailed();

        $this->assertSame($old, $user->fresh()->password);
        $this->assertSame('lost-device', $user->fresh()->remember_token);
    }

    public function test_finds_the_user_by_id(): void
    {
        $user = $this->user();

        $this->artisan('app:user-password', ['user' => (string) $user->id])
            ->expectsConfirmation(__('console.user_password.confirm', ['name' => 'Anna Keller', 'email' => 'anna@example.com']), 'yes')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), self::NEW_PASSWORD)
            ->assertSuccessful();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_ends_tokens_sessions_and_remembered_logins_but_keeps_the_second_factor(): void
    {
        // Tests use the array session driver; production uses the database.
        config(['session.driver' => 'database']);
        $user = $this->user();
        $other = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $user->createToken('laptop', ['mcp']);
        $other->createToken('other', ['mcp']);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 's2', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

        $this->change('anna@example.com')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), self::NEW_PASSWORD)
            ->expectsOutputToContain(__('console.user.access_ended', ['tokens' => 1, 'sessions' => 1]))
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());
        $this->assertNotSame('lost-device', $user->remember_token);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_says_so_when_the_sessions_are_not_in_the_database(): void
    {
        config(['session.driver' => 'file']);
        $this->user();

        $this->change('anna@example.com')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), self::NEW_PASSWORD)
            ->expectsOutputToContain(__('console.user.access_ended_no_sessions', ['tokens' => 0, 'driver' => 'file']))
            ->assertSuccessful();
    }

    public function test_writes_nothing_when_the_confirmation_is_declined(): void
    {
        $user = $this->user();
        $old = $user->password;

        $this->change('anna@example.com', 'no')->assertFailed();

        $this->assertSame($old, $user->fresh()->password);
        $this->assertSame('lost-device', $user->fresh()->remember_token);
    }

    public function test_an_unknown_user_is_an_error(): void
    {
        $this->user();

        $this->artisan('app:user-password', ['user' => 'nobody@example.com'])
            ->expectsOutputToContain(__('console.user.not_found', ['user' => 'nobody@example.com']))
            ->assertFailed();
    }

    public function test_without_a_terminal_nothing_is_written(): void
    {
        $user = $this->user();
        $old = $user->password;

        $this->artisan('app:user-password', ['user' => 'anna@example.com', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user_password.password_needs_interaction'))
            ->assertFailed();

        $this->assertSame($old, $user->fresh()->password);
    }

    public function test_writes_one_log_line_without_password_hash_or_address(): void
    {
        $user = $this->user();
        Log::spy();

        $this->change('anna@example.com')
            ->expectsQuestion(__('console.user_password.password'), self::NEW_PASSWORD)
            ->expectsQuestion(__('console.user_password.password_confirmation'), self::NEW_PASSWORD)
            ->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($user): bool {
            $text = json_encode($context);

            return $context['user_id'] === $user->id
                && $context['action'] === 'password'
                && ! str_contains($text, 'anna@example.com')
                && ! str_contains($text, self::NEW_PASSWORD)
                && ! str_contains($text, $user->fresh()->password);
        });
    }
}
