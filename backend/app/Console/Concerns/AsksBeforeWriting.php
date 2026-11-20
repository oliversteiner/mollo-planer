<?php

namespace App\Console\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\confirm;

/**
 * What every command that changes an account shares: a terminal, a question before anything is written, and one log
 * line. ManagesUserAccounts adds finding the user.
 */
trait AsksBeforeWriting
{
    /** Commands that write need a terminal: with --no-interaction nothing is written. */
    protected function requireTerminal(): bool
    {
        if (! $this->input->isInteractive()) {
            $this->components->error(__('console.user.needs_interaction'));

            return false;
        }

        return true;
    }

    protected function confirmWrite(string $question): bool
    {
        if (! confirm(label: $question, default: false)) {
            $this->components->warn(__('console.user.aborted'));

            return false;
        }

        return true;
    }

    /**
     * One line in the log: who was touched and by which command. Never a password, a hash or an address.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logIntervention(User $user, string $action, array $context = []): void
    {
        Log::warning('Admin command on a user account.', [
            'command' => $this->getName(),
            'action' => $action,
            'user_id' => $user->getKey(),
            'environment' => app()->environment(),
        ] + $context);
    }
}
