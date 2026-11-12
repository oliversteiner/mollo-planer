<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_self_registered_accounts_unconfirmed_for_longer_than_the_limit(): void
    {
        $expired = User::factory()->unapproved()->unverified()->create(['created_at' => now()->subDays(8)]);

        $this->artisan('app:prune-unverified-users')->assertSuccessful();

        $this->assertNull(User::find($expired->id));
    }

    public function test_keeps_the_accounts_that_are_still_within_time_or_not_pending(): void
    {
        $recent = User::factory()->unapproved()->unverified()->create(['created_at' => now()->subDays(3)]);
        $waitingForApproval = User::factory()->unapproved()->create(['created_at' => now()->subDays(30)]);
        $invitedAdmin = User::factory()->admin()->unverified()->create(['created_at' => now()->subDays(30)]);
        $member = User::factory()->create(['created_at' => now()->subDays(30)]);

        $this->artisan('app:prune-unverified-users')->assertSuccessful();

        foreach ([$recent, $waitingForApproval, $invitedAdmin, $member] as $user) {
            $this->assertNotNull(User::find($user->id));
        }
    }

    public function test_the_limit_can_be_changed_and_must_be_positive(): void
    {
        $account = User::factory()->unapproved()->unverified()->create(['created_at' => now()->subDays(3)]);

        $this->artisan('app:prune-unverified-users', ['--days' => 2])->assertSuccessful();
        $this->assertNull(User::find($account->id));

        $this->artisan('app:prune-unverified-users', ['--days' => 0])->assertFailed();
    }
}
