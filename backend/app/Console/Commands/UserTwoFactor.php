<?php

namespace App\Console\Commands;

use App\Console\Concerns\ManagesUserAccounts;
use App\Enums\TwoFactorAction;
use App\Models\User;
use App\Services\UserSecurity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\select;

/**
 * Looks at and changes the second factor of a user (lost device, lost recovery
 * codes). The app knows an authenticator app with recovery codes and passkeys.
 * Two-factor authentication stays mandatory: there is no switch and no
 * exception per user, the person sets up a new factor at the next login.
 *
 * The steps are in App\Services\UserSecurity, which the GraphQL mutation
 * resetUserTwoFactor uses as well.
 */
#[Signature('app:user-2fa {user : E-mail address or id of the user}
    {action? : status, reset, authenticator, passkeys or recovery-codes (without it you choose)}
    {--id= : With "passkeys": remove only the passkey with this id}')]
#[Description('Show or change the second factor of a user (authenticator app, recovery codes, passkeys)')]
class UserTwoFactor extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(UserSecurity $security): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        $action = $this->chooseAction();

        if ($action === null) {
            return self::FAILURE;
        }

        return match ($action) {
            TwoFactorAction::STATUS => $this->status($user, $security),
            TwoFactorAction::RESET => $this->reset($user, $security),
            TwoFactorAction::AUTHENTICATOR => $this->removeAuthenticator($user, $security),
            TwoFactorAction::PASSKEYS => $this->removePasskeys($user, $security),
            TwoFactorAction::RECOVERY_CODES => $this->recoveryCodes($user, $security),
        };
    }

    private function chooseAction(): ?TwoFactorAction
    {
        $typed = $this->argument('action');

        if ($typed === null) {
            if (! $this->input->isInteractive()) {
                $this->components->error(__('console.user_2fa.action_needs_interaction'));

                return null;
            }

            return TwoFactorAction::from((string) select(
                label: __('console.user_2fa.choose'),
                options: collect(TwoFactorAction::values())->mapWithKeys(fn (string $name): array => [$name => __("console.user_2fa.actions.$name")])->all(),
            ));
        }

        $action = TwoFactorAction::tryFrom((string) $typed);

        if ($action === null) {
            $this->components->error(__('console.user_2fa.unknown_action', ['action' => $typed, 'actions' => implode(', ', TwoFactorAction::values())]));
        }

        return $action;
    }

    private function status(User $user, UserSecurity $security): int
    {
        $status = $security->status($user);

        $this->components->twoColumnDetail(__('console.user_2fa.authenticator'), match (true) {
            $status['authenticator'] => __('console.user_2fa.authenticator_yes', ['date' => $status['confirmed_at']?->format('Y-m-d H:i')]),
            $status['authenticator_started'] => __('console.user_2fa.authenticator_started'),
            default => __('console.user_2fa.no'),
        });
        $this->components->twoColumnDetail(__('console.user_2fa.recovery_codes'), (string) $status['recovery_codes']);
        $this->components->twoColumnDetail(__('console.user_2fa.passkeys'), (string) count($status['passkeys']));

        foreach ($status['passkeys'] as $passkey) {
            $this->components->twoColumnDetail('  #'.$passkey['id'].' '.$passkey['name'], __('console.user_2fa.passkey_dates', [
                'created' => $passkey['created_at']?->format('Y-m-d') ?? '-',
                'used' => $passkey['last_used_at']?->format('Y-m-d') ?? __('console.user_2fa.never'),
            ]));
        }

        $this->components->twoColumnDetail(__('console.user_2fa.needs_setup'), $status['needs_setup'] ? __('console.user_2fa.yes') : __('console.user_2fa.no'));

        return self::SUCCESS;
    }

    private function reset(User $user, UserSecurity $security): int
    {
        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.user_2fa.confirm_reset', ['name' => $user->name]))) {
            return self::FAILURE;
        }

        // The service writes the log line and sends the mail (the mutation does the same).
        $ended = $security->resetTwoFactor($user, 'console');

        $this->components->info(__('console.user_2fa.done_reset', ['name' => $user->name]));
        $this->reportEndedAccess($ended);

        return self::SUCCESS;
    }

    private function removeAuthenticator(User $user, UserSecurity $security): int
    {
        if (! $security->hasAuthenticator($user) && $user->two_factor_secret === null) {
            $this->components->info(__('console.user_2fa.nothing_authenticator'));

            return self::SUCCESS;
        }

        // Only a confirmed authenticator is a factor; one that was just started is cleaned up quietly.
        $last = $security->hasAuthenticator($user) && $security->passkeyCount($user) === 0;

        if (! $this->confirmRemoval($user, __('console.user_2fa.confirm_authenticator', ['name' => $user->name]), $last)) {
            return self::FAILURE;
        }

        $security->removeAuthenticator($user);

        return $this->finishRemoval($user, $security, 'authenticator', $last, __('console.user_2fa.done_authenticator', ['name' => $user->name]));
    }

    private function removePasskeys(User $user, UserSecurity $security): int
    {
        $id = $this->option('id');

        if ($id !== null && (! ctype_digit((string) $id) || ! $user->passkeys()->whereKey((int) $id)->exists())) {
            $this->components->error(__('console.user_2fa.passkey_not_found', ['id' => $id]));

            return self::FAILURE;
        }

        $count = $id === null ? $security->passkeyCount($user) : 1;

        if ($count === 0) {
            $this->components->info(__('console.user_2fa.nothing_passkeys'));

            return self::SUCCESS;
        }

        $last = $security->passkeyCount($user) === $count && ! $security->hasAuthenticator($user);

        if (! $this->confirmRemoval($user, __('console.user_2fa.confirm_passkeys', ['name' => $user->name, 'count' => $count]), $last)) {
            return self::FAILURE;
        }

        $security->removePasskeys($user, $id === null ? null : (int) $id);

        return $this->finishRemoval($user, $security, 'passkeys', $last, __('console.user_2fa.done_passkeys', ['name' => $user->name, 'count' => $count]));
    }

    private function recoveryCodes(User $user, UserSecurity $security): int
    {
        if (! $security->hasAuthenticator($user)) {
            $this->components->error(__('console.user_2fa.recovery_needs_authenticator'));

            return self::FAILURE;
        }

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.user_2fa.confirm_recovery', ['name' => $user->name]))) {
            return self::FAILURE;
        }

        $codes = $security->newRecoveryCodes($user);

        $this->logIntervention($user, 'recovery-codes');
        $this->components->info(__('console.user_2fa.done_recovery', ['name' => $user->name, 'count' => count($codes)]));
        $this->components->warn(__('console.user_2fa.codes_once'));

        foreach ($codes as $code) {
            $this->line('  '.$code);
        }

        return self::SUCCESS;
    }

    /** The question before a removal; it says so when the person is left without any factor. */
    private function confirmRemoval(User $user, string $question, bool $last): bool
    {
        if (! $this->requireTerminal()) {
            return false;
        }

        return $this->confirmWrite($last ? $question.' '.__('console.user_2fa.last_factor') : $question);
    }

    /** When the last factor went, access ends too (the person must set up a new one). */
    private function finishRemoval(User $user, UserSecurity $security, string $action, bool $last, string $message): int
    {
        $ended = $last ? $security->revokeAccess($user) : null;

        $this->logIntervention($user, $action, ['last_factor' => $last]);
        $this->components->info($message);

        if ($ended !== null) {
            $this->reportEndedAccess($ended);
        }

        return self::SUCCESS;
    }
}
