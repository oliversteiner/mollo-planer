<?php

namespace App\Console\Commands;

use App\Console\Concerns\ManagesUserAccounts;
use App\Services\UserSecurity;
use App\Support\PasswordPolicy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\password;

/**
 * Sets a new password for a user (forgotten, and nobody can log in to change it).
 * The password is only asked for here, hidden; it never comes from the command
 * line or the code. Afterwards everything that kept the user signed in is ended.
 * The second factor stays as it is (see app:user-2fa).
 */
#[Signature('app:user-password {user : E-mail address or id of the user}')]
#[Description('Change the password of a user and end their access (asks interactively)')]
class UserPassword extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(UserSecurity $security): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error(__('console.user_password.password_needs_interaction'));

            return self::FAILURE;
        }

        if (! $this->confirmWrite(__('console.user_password.confirm', ['name' => $user->name, 'email' => $user->email]))) {
            return self::FAILURE;
        }

        $user->forceFill(['password' => $this->askForPassword()])->save();
        $ended = $security->revokeAccess($user);

        $this->logIntervention($user, 'password');
        $this->components->info(__('console.user_password.done', ['name' => $user->name]));
        $this->reportEndedAccess($ended);
        $this->components->info(__('console.user_password.two_factor_unchanged'));

        return self::SUCCESS;
    }

    private function askForPassword(): string
    {
        $password = password(
            label: __('console.user_password.password'),
            required: true,
            validate: ['password' => PasswordPolicy::rules()],
        );

        password(
            label: __('console.user_password.password_confirmation'),
            required: true,
            validate: fn (string $value): ?string => $value === $password ? null : __('console.user_password.password_mismatch'),
        );

        return $password;
    }
}
