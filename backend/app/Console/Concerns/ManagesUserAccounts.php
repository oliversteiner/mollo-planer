<?php

namespace App\Console\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * What the commands app:user-password, app:user-email and app:user-2fa share:
 * finding the user, asking before anything is written, and one log line.
 */
trait ManagesUserAccounts
{
    /** The user by e-mail address (any case) or by id. */
    protected function findUser(string $needle): ?User
    {
        $needle = trim($needle);

        if ($needle === '') {
            return null;
        }

        return ctype_digit($needle)
            ? User::query()->find((int) $needle)
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($needle)])->first();
    }

    /** The user of the argument, or an error message (null, the command fails). */
    protected function userOrFail(): ?User
    {
        $argument = (string) $this->argument('user');
        $user = $this->findUser($argument);

        if ($user === null) {
            $this->components->error(__('console.user.not_found', ['user' => $argument]));
        }

        return $user;
    }

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

    /** What was ended, in one line for the output. */
    protected function reportEndedAccess(array $ended): void
    {
        $this->components->info($ended['sessions'] === null
            ? __('console.user.access_ended_no_sessions', ['tokens' => $ended['tokens'], 'driver' => config('session.driver')])
            : __('console.user.access_ended', ['tokens' => $ended['tokens'], 'sessions' => $ended['sessions']]));
    }

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['user' => fn (): string => text(label: __('console.user.user_prompt'), required: true)];
    }
}
