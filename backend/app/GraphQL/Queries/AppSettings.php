<?php

namespace App\GraphQL\Queries;

use App\Support\AppSettings as Settings;

/**
 * Query.appSettings and the result of updateAppSettings.
 */
class AppSettings
{
    /**
     * @return array{twoFactorEnabled: bool, twoFactorSwitchable: bool}
     */
    public function __invoke(): array
    {
        return [
            'twoFactorEnabled' => Settings::twoFactorEnabled(),
            'twoFactorSwitchable' => Settings::twoFactorSwitchable(),
        ];
    }
}
