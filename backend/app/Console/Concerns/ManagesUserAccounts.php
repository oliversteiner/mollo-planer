<?php

namespace App\Console\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Log;

use function Laravel\Prompts\text;

/**
 * What the commands app:user-password, app:user-email and app:user-2fa share:
 * finding the user, asking before anything is written, and one log line.
 */
trait ManagesUserAccounts
{
    use AsksBeforeWriting;

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
