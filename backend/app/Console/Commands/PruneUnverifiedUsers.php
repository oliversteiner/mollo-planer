<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes self-registered accounts whose mail address was never confirmed
 * within the given number of days. Accounts created by an admin are never
 * touched (they are confirmed by their invitation), and neither are accounts
 * that are confirmed but still wait for approval.
 */
#[Signature('app:prune-unverified-users {--days=7 : Days after registration before an unconfirmed account is deleted}')]
#[Description('Delete self-registered accounts whose mail address was not confirmed in time')]
class PruneUnverifiedUsers extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->components->error(__('console.prune_unverified.invalid_days'));

            return self::FAILURE;
        }

        $expired = User::query()
            ->whereNull('email_verified_at')
            ->whereNull('approved_at')
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        $expired->each->delete();
        $deleted = $expired->count();

        $this->components->info(__('console.prune_unverified.done', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
