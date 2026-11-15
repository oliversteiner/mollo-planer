<?php

namespace App\Support;

use App\Enums\ProjectRole;
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

    private const PROJECT_CREATOR_ROLE = 'project_creator_role';

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

    /**
     * The project role somebody gets in a project they create (standard:
     * owner). A stored key that is no role any more falls back to owner.
     */
    public static function projectCreatorRole(): ProjectRole
    {
        $key = AppSetting::query()->where('key', self::PROJECT_CREATOR_ROLE)->value('value');

        return ProjectRole::tryFrom((string) $key) ?? ProjectRole::OWNER;
    }

    public static function setProjectCreatorRole(ProjectRole $role): void
    {
        AppSetting::query()->updateOrCreate(['key' => self::PROJECT_CREATOR_ROLE], ['value' => $role->value]);
    }
}
