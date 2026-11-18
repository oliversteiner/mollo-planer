<?php

namespace App\Console\Commands;

use App\Console\Concerns\ManagesUserAccounts;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Shows or changes the e-mail address of a user and whether it counts as
 * confirmed. On the server an admin acts, so a changed address is confirmed by
 * default (the app itself asks the person to confirm it again, see
 * UpdateUserProfileInformation); --unverified takes that back.
 *
 * What an unconfirmed address means in the app: login works, but the GraphQL
 * API stays locked until it is confirmed (EnsureEmailVerified); accounts that
 * were never confirmed and never approved are deleted by app:prune-unverified-users.
 */
#[Signature('app:user-email {user : E-mail address or id of the user}
    {email? : The new address (without it the command only shows the current state)}
    {--verified : Mark the address as confirmed (keeps an earlier date)}
    {--unverified : Mark the address as not confirmed, the person has to confirm it again}')]
#[Description('Show or change the e-mail address of a user and its confirmation')]
class UserEmail extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        if ($this->option('verified') && $this->option('unverified')) {
            $this->components->error(__('console.user_email.both_options'));

            return self::FAILURE;
        }

        $new = trim((string) $this->argument('email'));

        if ($new === '' && ! $this->option('verified') && ! $this->option('unverified')) {
            $this->components->info(__('console.user_email.state', ['email' => $user->email, 'verified' => $this->describe($user->email_verified_at)]));

            return self::SUCCESS;
        }

        if ($new !== '' && ! $this->validAddress($user, $new)) {
            return self::FAILURE;
        }

        $oldEmail = $user->email;
        $oldVerified = $user->email_verified_at;
        $email = $new !== '' ? $new : $oldEmail;
        $verified = $this->targetVerification($oldVerified, $email !== $oldEmail);

        if ($email === $oldEmail && ($verified === null) === ($oldVerified === null)) {
            $this->components->info(__('console.user_email.nothing'));

            return self::SUCCESS;
        }

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.user_email.confirm', ['name' => $user->name]))) {
            return self::FAILURE;
        }

        $user->forceFill(['email' => $email, 'email_verified_at' => $verified])->save();

        $this->logIntervention($user, 'email', ['address_changed' => $email !== $oldEmail, 'verified' => $verified !== null]);
        $this->components->info(__('console.user_email.done', [
            'old' => $oldEmail,
            'new' => $email,
            'old_verified' => $this->describe($oldVerified),
            'new_verified' => $this->describe($verified),
        ]));

        if ($verified === null) {
            $this->warnAboutUnverified($user);
        }

        return self::SUCCESS;
    }

    /** The new confirmation date: --unverified clears it, --verified or a changed address sets it (an earlier date stays). */
    private function targetVerification(?Carbon $current, bool $addressChanged): ?Carbon
    {
        if ($this->option('unverified')) {
            return null;
        }

        return ($this->option('verified') || $addressChanged) ? ($current ?? now()) : $current;
    }

    /** Same rules as CreateUserInput, and unique without regard to case (the database may or may not care). */
    private function validAddress(User $user, string $email): bool
    {
        $validator = Validator::make(['email' => $email], ['email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email')->ignore($user->getKey()),
            fn (string $attribute, mixed $value, \Closure $fail) => User::query()
                ->whereKeyNot($user->getKey())
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $value)])
                ->exists() ? $fail(__('validation.unique', ['attribute' => $attribute])) : null,
        ]]);

        foreach ($validator->errors()->all() as $message) {
            $this->components->error($message);
        }

        return ! $validator->fails();
    }

    private function warnAboutUnverified(User $user): void
    {
        $this->components->warn(__('console.user_email.locked'));

        if ($user->approved_at === null) {
            $this->components->warn(__('console.user_email.prune', [
                'days' => PruneUnverifiedUsers::DEFAULT_DAYS,
                'age' => (int) $user->created_at?->diffInDays(now()),
            ]));
        }
    }

    private function describe(?Carbon $verifiedAt): string
    {
        return $verifiedAt === null ? __('console.user_email.no') : __('console.user_email.yes', ['date' => $verifiedAt->format('Y-m-d H:i')]);
    }
}
