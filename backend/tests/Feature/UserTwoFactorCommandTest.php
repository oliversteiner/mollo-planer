<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

class UserTwoFactorCommandTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_CODES = ['old-code-1', 'old-code-2'];

    private function user(bool $authenticator = true, int $passkeys = 0): User
    {
        $user = User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', 'remember_token' => 'lost-device']);

        if ($authenticator) {
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt('secret'),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(self::OLD_CODES)),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        for ($i = 1; $i <= $passkeys; $i++) {
            $user->passkeys()->forceCreate(['name' => "Key $i", 'credential_id' => "cred-$i", 'credential' => []]);
        }

        $user->createToken('laptop', ['mcp']);

        return $user->fresh();
    }

    private function act(string $action, string $question, string $answer = 'yes', array $options = []): PendingCommand
    {
        return $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => $action, ...$options])
            ->expectsConfirmation($question, $answer);
    }

    private function annaName(): array
    {
        return ['name' => 'Anna Keller'];
    }

    public function test_status_shows_everything_and_writes_nothing_even_without_a_terminal(): void
    {
        $user = $this->user(true, 2);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'status', '--no-interaction' => true])
            ->expectsOutputToContain('#'.$user->passkeys()->where('name', 'Key 1')->value('id').' Key 1')
            ->expectsOutputToContain('#'.$user->passkeys()->where('name', 'Key 2')->value('id').' Key 2')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertSame(2, $user->passkeys()->count());
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('lost-device', $user->remember_token);
    }

    public function test_status_says_when_a_new_factor_has_to_be_set_up(): void
    {
        $this->user(false);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'status'])
            ->expectsOutputToContain(__('console.user_2fa.needs_setup'))
            ->assertSuccessful();
    }

    public function test_reset_removes_every_part_and_ends_access(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->user(true, 2);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->act('reset', __('console.user_2fa.confirm_reset', $this->annaName()))
            ->expectsOutputToContain(__('console.user.access_ended', ['tokens' => 1, 'sessions' => 1]))
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame(0, $user->passkeys()->count());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertNotSame('lost-device', $user->remember_token);
    }

    public function test_removing_the_authenticator_keeps_passkeys_and_access(): void
    {
        $user = $this->user(true, 1);

        $this->act('authenticator', __('console.user_2fa.confirm_authenticator', $this->annaName()))->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame(1, $user->passkeys()->count());
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('lost-device', $user->remember_token);
    }

    public function test_removing_the_last_factor_says_so_and_ends_access(): void
    {
        $user = $this->user(true, 0);

        $this->act('authenticator', __('console.user_2fa.confirm_authenticator', $this->annaName()).' '.__('console.user_2fa.last_factor'))
            ->expectsOutputToContain(__('console.user.access_ended_no_sessions', ['tokens' => 1, 'driver' => config('session.driver')]))
            ->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('lost-device', $user->remember_token);
    }

    public function test_an_authenticator_that_was_only_started_is_no_factor_and_cleaned_up_quietly(): void
    {
        $user = $this->user(false, 1);
        $user->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('half')])->save();

        $this->act('authenticator', __('console.user_2fa.confirm_authenticator', $this->annaName()))->assertSuccessful();

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_nothing_to_remove_is_not_an_error(): void
    {
        $this->user(false);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'authenticator'])
            ->expectsOutputToContain(__('console.user_2fa.nothing_authenticator'))
            ->assertSuccessful();
        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'passkeys'])
            ->expectsOutputToContain(__('console.user_2fa.nothing_passkeys'))
            ->assertSuccessful();
    }

    public function test_removing_all_passkeys_keeps_the_authenticator_and_access(): void
    {
        $user = $this->user(true, 2);

        $this->act('passkeys', __('console.user_2fa.confirm_passkeys', ['name' => 'Anna Keller', 'count' => 2]))->assertSuccessful();

        $user->refresh();
        $this->assertSame(0, $user->passkeys()->count());
        $this->assertNotNull($user->two_factor_secret);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('lost-device', $user->remember_token);
    }

    public function test_removing_one_passkey_by_id_leaves_the_others(): void
    {
        $user = $this->user(false, 2);
        $second = $user->passkeys()->where('name', 'Key 2')->firstOrFail();

        $this->act('passkeys', __('console.user_2fa.confirm_passkeys', ['name' => 'Anna Keller', 'count' => 1]), 'yes', ['--id' => (string) $second->id])->assertSuccessful();

        $this->assertSame(['Key 1'], $user->passkeys()->pluck('name')->all());
        $this->assertSame(1, $user->tokens()->count(), 'another passkey is left: no last factor');
    }

    public function test_removing_the_last_passkey_without_an_authenticator_ends_access(): void
    {
        $user = $this->user(false, 1);

        $this->act('passkeys', __('console.user_2fa.confirm_passkeys', ['name' => 'Anna Keller', 'count' => 1]).' '.__('console.user_2fa.last_factor'))->assertSuccessful();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame('lost-device', $user->fresh()->remember_token);
    }

    public function test_a_passkey_of_another_user_or_an_unknown_id_is_refused(): void
    {
        $this->user(true, 1);
        $other = User::factory()->create();
        $foreign = $other->passkeys()->forceCreate(['name' => 'Foreign', 'credential_id' => 'foreign', 'credential' => []]);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'passkeys', '--id' => (string) $foreign->id])
            ->expectsOutputToContain(__('console.user_2fa.passkey_not_found', ['id' => $foreign->id]))
            ->assertFailed();
        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'passkeys', '--id' => 'abc'])->assertFailed();

        $this->assertSame(1, $other->passkeys()->count());
    }

    public function test_new_recovery_codes_replace_the_old_ones_and_are_shown_once(): void
    {
        $user = $this->user(true);
        $stored = $user->two_factor_recovery_codes;
        Log::spy();

        $this->act('recovery-codes', __('console.user_2fa.confirm_recovery', $this->annaName()))
            ->expectsOutputToContain(__('console.user_2fa.codes_once'))
            ->assertSuccessful();

        $user->refresh();
        $codes = $user->recoveryCodes();
        $this->assertCount(8, $codes);
        $this->assertSame([], array_intersect($codes, self::OLD_CODES));
        $this->assertNotSame($stored, $user->two_factor_recovery_codes);
        $this->assertSame(1, $user->tokens()->count());

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['action'] === 'recovery-codes'
            && collect($codes)->every(fn (string $code): bool => ! str_contains(json_encode($context), $code)));
    }

    public function test_recovery_codes_need_a_confirmed_authenticator(): void
    {
        $user = $this->user(false, 1);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'recovery-codes'])
            ->expectsOutputToContain(__('console.user_2fa.recovery_needs_authenticator'))
            ->assertFailed();

        $this->assertNull($user->fresh()->two_factor_recovery_codes);
    }

    public function test_declining_or_having_no_terminal_changes_nothing(): void
    {
        $user = $this->user(true, 1);

        $this->act('reset', __('console.user_2fa.confirm_reset', $this->annaName()), 'no')->assertFailed();
        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'reset', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user.needs_interaction'))
            ->assertFailed();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertSame(1, $user->passkeys()->count());
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_the_action_is_chosen_by_a_prompt_when_it_is_missing(): void
    {
        $this->user(true, 1);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com'])
            ->expectsChoice(__('console.user_2fa.choose'), 'status', collect(['status', 'reset', 'authenticator', 'passkeys', 'recovery-codes'])->mapWithKeys(fn (string $name): array => [$name => __("console.user_2fa.actions.$name")])->all())
            ->assertSuccessful();
    }

    public function test_without_a_terminal_the_action_must_be_given_and_unknown_ones_are_refused(): void
    {
        $this->user(true);

        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user_2fa.action_needs_interaction'))
            ->assertFailed();
        $this->artisan('app:user-2fa', ['user' => 'anna@example.com', 'action' => 'disable'])->assertFailed();
    }

    public function test_an_unknown_user_is_an_error(): void
    {
        $this->artisan('app:user-2fa', ['user' => 'nobody@example.com', 'action' => 'status'])->assertFailed();
    }

    public function test_the_log_line_has_the_user_and_the_action_but_no_address(): void
    {
        $user = $this->user(true, 0);
        Log::spy();

        $this->act('reset', __('console.user_2fa.confirm_reset', $this->annaName()))->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['user_id'] === $user->id
            && $context['action'] === 'reset'
            && ! str_contains(json_encode($context), 'example.com'));
    }
}
