<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\GraphQL\Queries\AppSettings as AppSettingsQuery;
use App\Support\AppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Switches two-factor authentication on or off for the whole application
 * (admins only, checked by @can). Refused in production.
 */
class UpdateAppSettings
{
    use ResolvesViewer;

    /**
     * @param  array{twoFactorEnabled: bool}  $args
     * @return array{twoFactorEnabled: bool, twoFactorSwitchable: bool}
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        if (! AppSettings::twoFactorSwitchable()) {
            throw ValidationException::withMessages(['twoFactorEnabled' => __('graphql.two_factor_not_switchable')]);
        }

        $viewer = $this->viewer($context);
        DB::transaction(fn () => AppSettings::setTwoFactorEnabled($args['twoFactorEnabled'], $viewer));

        return (new AppSettingsQuery)();
    }
}
