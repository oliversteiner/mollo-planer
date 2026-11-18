<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\SecurityChange;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

/**
 * One notice mail for each change of the sign-in, by event (not at each place that changes it):
 * a new password (reset by mail, change in the profile), the authenticator app (set up, removed), a
 * passkey (added, removed), new recovery codes. Also the very first factor: after an admin reset only the
 * password protects the account, and the mail is the only hint for the owner if somebody else sets it up.
 *
 * Fortify fires "enabled" when the setup starts and "confirmed" when it ends: only "confirmed" counts, and
 * the codes that come with the setup do not give a mail of their own. The reset by an admin changes the
 * factors directly and has its own mail (UserSecurity).
 */
class NotifyOfSecurityChange
{
    public function handle(
        PasswordReset|PasswordUpdatedViaController|TwoFactorAuthenticationConfirmed|TwoFactorAuthenticationDisabled|PasskeyRegistered|PasskeyDeleted|RecoveryCodesGenerated $event,
    ): void {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->notify((new SecurityChange($this->kind($event)))->locale($user->mailLocale()));
    }

    private function kind(object $event): string
    {
        return match (true) {
            $event instanceof PasswordReset, $event instanceof PasswordUpdatedViaController => SecurityChange::PASSWORD,
            $event instanceof TwoFactorAuthenticationConfirmed => SecurityChange::AUTHENTICATOR_ADDED,
            $event instanceof TwoFactorAuthenticationDisabled => SecurityChange::AUTHENTICATOR_REMOVED,
            $event instanceof PasskeyRegistered => SecurityChange::PASSKEY_ADDED,
            $event instanceof PasskeyDeleted => SecurityChange::PASSKEY_REMOVED,
            default => SecurityChange::RECOVERY_CODES,
        };
    }
}
