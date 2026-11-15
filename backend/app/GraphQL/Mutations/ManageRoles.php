<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Role;
use App\Services\RoleAdministration;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Roles in the settings (right roles.manage, checked by @can). The rules
 * are in App\Services\RoleAdministration.
 */
class ManageRoles
{
    use ResolvesViewer;

    public function __construct(private readonly RoleAdministration $administration) {}

    /**
     * @param  array{input: array{scope: string, names: array{de: string, en: string}, functionIds: list<int|string>, projectCap?: list<string>|null}}  $args
     */
    public function create(mixed $root, array $args, GraphQLContext $context): Role
    {
        $input = $args['input'];

        return $this->administration->createRole(
            $this->viewer($context),
            $input['scope'],
            $input['names'],
            $input['functionIds'],
            $input['projectCap'] ?? null,
        );
    }

    /**
     * @param  array{id: int|string, input: array<string, mixed>}  $args
     */
    public function update(mixed $root, array $args, GraphQLContext $context): Role
    {
        return $this->administration->updateRole($this->viewer($context), $args['id'], $args['input']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function duplicate(mixed $root, array $args, GraphQLContext $context): Role
    {
        return $this->administration->duplicateRole($this->viewer($context), $args['id']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function delete(mixed $root, array $args, GraphQLContext $context): Role
    {
        return $this->administration->deleteRole($this->viewer($context), $args['id']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function setDefault(mixed $root, array $args, GraphQLContext $context): Role
    {
        return $this->administration->setDefaultRole($this->viewer($context), $args['id']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function reset(mixed $root, array $args, GraphQLContext $context): Role
    {
        return $this->administration->resetRole($this->viewer($context), $args['id']);
    }
}
