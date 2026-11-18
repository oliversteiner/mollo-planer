<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SecurityChange;
use App\Support\McpTokens;
use App\Support\StrongAuthentication;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * The second factor and the access of a user, in one place: the GraphQL
 * mutation resetUserTwoFactor and the artisan commands (app:user-password,
 * app:user-2fa) use the same steps.
 *
 * The app knows three parts: the authenticator app (two_factor_*) with its
 * recovery codes, and passkeys. Two-factor authentication is mandatory, so a
 * user without any factor is led to the setup at the next login.
 */
class UserSecurity
{
    /**
     * @return array{
     *     authenticator: bool,
     *     authenticator_started: bool,
     *     confirmed_at: ?CarbonInterface,
     *     recovery_codes: int,
     *     passkeys: list<array{id: int, name: string, created_at: ?CarbonInterface, last_used_at: ?CarbonInterface}>,
     *     needs_setup: bool,
     * }
     */
    public function status(User $user): array
    {
        return [
            'authenticator' => $this->hasAuthenticator($user),
            'authenticator_started' => $user->two_factor_secret !== null && ! $this->hasAuthenticator($user),
            'confirmed_at' => $user->two_factor_confirmed_at,
            'recovery_codes' => $this->recoveryCodeCount($user),
            'passkeys' => $user->passkeys()->orderBy('id')->get()
                ->map(fn ($passkey): array => [
                    'id' => (int) $passkey->getKey(),
                    'name' => (string) $passkey->name,
                    'created_at' => $passkey->created_at,
                    'last_used_at' => $passkey->last_used_at,
                ])->all(),
            'needs_setup' => StrongAuthentication::needsSetup($user),
        ];
    }

    /** A confirmed authenticator app counts as a factor, one that was only started does not. */
    public function hasAuthenticator(User $user): bool
    {
        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
    }

    public function passkeyCount(User $user): int
    {
        return $user->passkeys()->count();
    }

    public function recoveryCodeCount(User $user): int
    {
        if ($user->two_factor_recovery_codes === null) {
            return 0;
        }

        return count($user->recoveryCodes());
    }

    /**
     * The user lost their device: authenticator, recovery codes, passkeys, and
     * everything that stays signed in, so the next login sets up a new factor.
     *
     * @param  string  $by  Who did it: the id of the admin (mutation) or "console" (artisan), for the log
     * @return array{tokens: int, sessions: ?int}
     */
    public function resetTwoFactor(User $user, string $by = 'console'): array
    {
        $ended = DB::transaction(function () use ($user): array {
            $this->removeAuthenticator($user);
            $this->removePasskeys($user);

            return $this->revokeAccess($user);
        });

        // The only way around the second factor must not go unnoticed: the person hears of it (in their own
        // language, not the admin's) and the log names who did it, never an address or a secret.
        $user->notify((new SecurityChange(SecurityChange::ADMIN_RESET))->locale($user->accountLocale()));
        Log::warning('Second factor of a user reset.', [
            'action' => 'reset',
            'by' => $by,
            'user_id' => $user->getKey(),
            'environment' => app()->environment(),
        ]);

        return $ended;
    }

    /** Removes the authenticator app and its recovery codes. Passkeys stay. */
    public function removeAuthenticator(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** Removes all passkeys of the user, or only the one with the given id. Returns how many went. */
    public function removePasskeys(User $user, ?int $id = null): int
    {
        $query = $user->passkeys();

        if ($id !== null) {
            $query->whereKey($id);
        }

        return $query->delete();
    }

    /**
     * Ends what keeps the user signed in: the access tokens for Claude, the
     * "remember me" logins (a new remember token, also on a lost device) and
     * the sessions. Sessions can only be ended when they are in the database;
     * otherwise `sessions` is null.
     *
     * @return array{tokens: int, sessions: ?int}
     */
    public function revokeAccess(User $user): array
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $tokens = McpTokens::revokeAll($user);
        $sessions = null;

        if (config('session.driver') === 'database') {
            $sessions = DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        return ['tokens' => $tokens, 'sessions' => $sessions];
    }

    /**
     * New recovery codes (the old ones expire), the same way the app makes them.
     * Only for a confirmed authenticator. The codes are returned once, never stored in clear.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(User $user): array
    {
        app(GenerateNewRecoveryCodes::class)($user);

        return $user->fresh()->recoveryCodes();
    }
}
