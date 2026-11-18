<?php

namespace Tests\Feature;

use App\Console\Commands\PruneUnverifiedUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class UserEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', 'email_verified_at' => now()->subWeek(), ...$attributes]);
    }

    private function change(array $arguments, string $answer = 'yes'): PendingCommand
    {
        return $this->artisan('app:user-email', $arguments)
            ->expectsConfirmation(__('console.user_email.confirm', ['name' => 'Anna Keller']), $answer);
    }

    public function test_without_an_address_or_option_it_only_shows_the_state_and_needs_no_terminal(): void
    {
        $user = $this->user(['email_verified_at' => null]);

        $this->artisan('app:user-email', ['user' => 'anna@example.com', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user_email.state', ['email' => 'anna@example.com', 'verified' => __('console.user_email.no')]))
            ->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_changed_address_is_confirmed_by_default(): void
    {
        $user = $this->user(['email_verified_at' => null, 'approved_at' => now()]);

        $this->change(['user' => 'anna@example.com', 'email' => 'anna.keller@example.org'])->assertSuccessful();

        $user->refresh();
        $this->assertSame('anna.keller@example.org', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a_changed_address_keeps_an_earlier_confirmation_date(): void
    {
        $date = now()->subMonth()->startOfSecond();
        $user = $this->user(['email_verified_at' => $date]);

        $this->change(['user' => (string) $user->id, 'email' => 'neu@example.org'])->assertSuccessful();

        $this->assertTrue($date->equalTo($user->fresh()->email_verified_at));
    }

    public function test_the_output_names_the_old_and_the_new_value_in_one_line(): void
    {
        $this->user(['email_verified_at' => null]);

        $this->change(['user' => 'anna@example.com', 'email' => 'neu@example.org'])
            ->expectsOutputToContain('anna@example.com → neu@example.org')
            ->assertSuccessful();
    }

    public function test_unverified_clears_the_confirmation_and_warns_that_the_api_is_locked(): void
    {
        $user = $this->user(['approved_at' => now()]);

        $this->change(['user' => 'anna@example.com', '--unverified' => true])
            ->expectsOutputToContain(__('console.user_email.locked'))
            ->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_unverified_with_a_new_address_changes_both(): void
    {
        $user = $this->user(['approved_at' => now()]);

        $this->change(['user' => 'anna@example.com', 'email' => 'neu@example.org', '--unverified' => true])->assertSuccessful();

        $user->refresh();
        $this->assertSame('neu@example.org', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_an_unapproved_unconfirmed_account_is_warned_about_the_cleanup(): void
    {
        $user = $this->user(['approved_at' => null, 'created_at' => now()->subDays(20)]);

        $this->change(['user' => 'anna@example.com', '--unverified' => true])
            ->expectsOutputToContain(__('console.user_email.prune', ['days' => PruneUnverifiedUsers::DEFAULT_DAYS, 'age' => 20]))
            ->assertSuccessful();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verified_sets_the_date_when_it_is_empty_and_keeps_an_earlier_one(): void
    {
        $empty = $this->user(['email_verified_at' => null]);

        $this->change(['user' => 'anna@example.com', '--verified' => true])->assertSuccessful();
        $this->assertNotNull($empty->fresh()->email_verified_at);

        $date = now()->subYear()->startOfSecond();
        $empty->forceFill(['email_verified_at' => $date])->save();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', '--verified' => true])
            ->expectsOutputToContain(__('console.user_email.nothing'))
            ->assertSuccessful();
        $this->assertTrue($date->equalTo($empty->fresh()->email_verified_at));
    }

    public function test_both_options_together_are_an_error(): void
    {
        $user = $this->user();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', '--verified' => true, '--unverified' => true])
            ->expectsOutputToContain(__('console.user_email.both_options'))
            ->assertFailed();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_address_of_another_user_is_refused_also_in_another_case(): void
    {
        User::factory()->create(['email' => 'taken@example.org']);
        $user = $this->user();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', 'email' => 'TAKEN@example.org'])->assertFailed();

        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_an_invalid_address_is_refused(): void
    {
        $user = $this->user();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', 'email' => 'not-an-address'])->assertFailed();

        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_the_same_address_changes_nothing(): void
    {
        $this->user();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', 'email' => 'anna@example.com'])
            ->expectsOutputToContain(__('console.user_email.nothing'))
            ->assertSuccessful();
    }

    public function test_the_own_address_in_another_case_is_a_valid_change(): void
    {
        $user = $this->user();

        $this->change(['user' => 'anna@example.com', 'email' => 'Anna@Example.com'])->assertSuccessful();

        $this->assertSame('Anna@Example.com', $user->fresh()->email);
    }

    public function test_writing_needs_a_terminal_and_a_yes(): void
    {
        $user = $this->user();

        $this->artisan('app:user-email', ['user' => 'anna@example.com', 'email' => 'neu@example.org', '--no-interaction' => true])
            ->expectsOutputToContain(__('console.user.needs_interaction'))
            ->assertFailed();
        $this->change(['user' => 'anna@example.com', 'email' => 'neu@example.org'], 'no')->assertFailed();

        $this->assertSame('anna@example.com', $user->fresh()->email);
    }

    public function test_the_access_of_the_user_stays(): void
    {
        $user = $this->user();
        $user->createToken('laptop', ['mcp']);

        $this->change(['user' => 'anna@example.com', 'email' => 'neu@example.org'])->assertSuccessful();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_an_unknown_user_is_an_error(): void
    {
        $this->artisan('app:user-email', ['user' => 'nobody@example.com'])->assertFailed();
    }

    public function test_the_log_line_has_the_user_but_no_address(): void
    {
        $user = $this->user();
        Log::spy();

        $this->change(['user' => 'anna@example.com', 'email' => 'neu@example.org'])->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($user): bool {
            $text = json_encode($context);

            return $context['user_id'] === $user->id && ! str_contains($text, 'example');
        });
    }
}
