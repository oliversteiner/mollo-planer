<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Settings of the whole application that admins change at runtime.
 *
 * Two-factor authentication: on by default. Outside production an admin
 * may switch it off completely (no code at login, no setup, no lock of the
 * GraphQL API), to make local testing easier. In production it is always
 * on, whatever the database says, so a taken-over admin account cannot
 * switch the protection off.
 */
final class AppSettings
{
    private const TWO_FACTOR_ENABLED = 'two_factor_enabled';

    /**
     * Is a second factor required? Checked by the login and both locks.
     */
    public static function twoFactorEnabled(): bool
    {
        if (! self::twoFactorSwitchable()) {
            return true;
        }

        return (bool) (AppSetting::query()->where('key', self::TWO_FACTOR_ENABLED)->value('value') ?? true);
    }

    /**
     * May the setting be changed here? Never in production.
     */
    public static function twoFactorSwitchable(): bool
    {
        return ! app()->isProduction();
    }

    public static function setTwoFactorEnabled(bool $enabled, User $admin): void
    {
        AppSetting::query()->updateOrCreate(['key' => self::TWO_FACTOR_ENABLED], ['value' => $enabled]);

        Log::warning('Two-factor authentication '.($enabled ? 'enabled' : 'disabled').' for the whole application.', [
            'admin_id' => $admin->getKey(),
            'environment' => app()->environment(),
        ]);
    }
}
