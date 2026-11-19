<?php

namespace App\GraphQL\Queries;

use App\Models\Role;
use App\Support\AppSettings as Settings;
use App\Support\DeploymentEnvironment;

/**
 * Query.appSettings and the result of updateAppSettings.
 */
class AppSettings
{
    /**
     * @return array{twoFactorEnabled: bool, twoFactorSwitchable: bool, isTestEnvironment: bool, projectCreatorRole: Role}
     */
    public function __invoke(): array
    {
        return [
            'twoFactorEnabled' => Settings::twoFactorEnabled(),
            'twoFactorSwitchable' => Settings::twoFactorSwitchable(),
            'isTestEnvironment' => DeploymentEnvironment::showsTestBanner(),
            'projectCreatorRole' => Settings::projectCreatorRole(),
        ];
    }
}
