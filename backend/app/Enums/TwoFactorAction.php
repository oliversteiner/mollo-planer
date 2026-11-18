<?php

namespace App\Enums;

/** The actions of app:user-2fa. The value is what is typed on the command line. */
enum TwoFactorAction: string
{
    case STATUS = 'status';
    case RESET = 'reset';
    case AUTHENTICATOR = 'authenticator';
    case PASSKEYS = 'passkeys';
    case RECOVERY_CODES = 'recovery-codes';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $action): string => $action->value, self::cases());
    }
}
