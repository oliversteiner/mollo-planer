<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

/**
 * The rule for passwords in one place (docs: Auftrag 22 O4). Registration, reset, invitation, change in
 * the profile and the artisan commands all use it through Password::default().
 *
 * - at least 12 characters, no rules about the make-up (no digit or symbol required; pasting stays allowed);
 * - at most 72 bytes: bcrypt reads no more, and a longer one would be cut off silently, so it is refused
 *   with a clear message instead (72 bytes are at least 64 characters, the usual minimum for a limit);
 * - not in a known leak (haveibeenpwned, by the first characters of the hash only). When the service
 *   does not answer (3 seconds at most), the password is accepted. The check can be switched off with
 *   PASSWORD_UNCOMPROMISED_CHECK=false, for environments without a network (browser tests, tests).
 *
 * Existing accounts are not touched.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const MAX_BYTES = 72;

    /** How long the leak check may take before it counts as "not answered". */
    public const CHECK_TIMEOUT_SECONDS = 3;

    public static function defaults(): Password
    {
        $rule = Password::min(self::MIN_LENGTH);

        return config('auth.password_uncompromised', true) ? $rule->uncompromised() : $rule;
    }

    /** The limit in bytes, as a rule next to the default ones. */
    public static function maxBytes(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && strlen($value) > self::MAX_BYTES) {
                $fail(__('validation.password_too_long', ['max' => self::MAX_BYTES]));
            }
        };
    }

    /**
     * Everything a field for a new password needs, for the prompts of the artisan commands and the like.
     *
     * @return list<mixed>
     */
    public static function rules(): array
    {
        return ['required', 'string', Password::default(), self::maxBytes()];
    }

    /** The check with a short timeout in place of Laravel's 30 seconds. */
    public static function verifier(): Closure
    {
        return fn (UncompromisedVerifier $verifier, $app): UncompromisedVerifier => new NotPwnedVerifier($app->make(Factory::class), self::CHECK_TIMEOUT_SECONDS);
    }
}
