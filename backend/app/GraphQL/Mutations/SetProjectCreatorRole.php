<?php

namespace App\GraphQL\Mutations;

use App\Enums\Right;
use App\GraphQL\Queries\AppSettings as AppSettingsQuery;
use App\Models\Role;
use App\Support\AppSettings;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the project role of the person who creates a project (admins only,
 * checked by @can). The role must carry project.delete, so that every new
 * project has someone who may delete it (docs/rollen-funktionen-rechte.md, §7.3).
 */
class SetProjectCreatorRole
{
    /**
     * @param  array{roleKey: string}  $args
     * @return array{twoFactorEnabled: bool, twoFactorSwitchable: bool, projectCreatorRole: Role}
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $root, array $args): array
    {
        $role = Role::resolve(Role::SCOPE_PROJECT, $args['roleKey'])
            ?? throw ValidationException::withMessages(['roleKey' => __('validation.exists', ['attribute' => 'roleKey'])]);

        if (! Rights::projectRoleAllows($role, Right::PROJECT_DELETE)) {
            throw ValidationException::withMessages(['roleKey' => __('graphql.creator_role_needs_delete')]);
        }

        DB::transaction(fn () => AppSettings::setProjectCreatorRole($role));

        return (new AppSettingsQuery)();
    }
}
