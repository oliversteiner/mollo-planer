<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\RoleFunction;
use App\Services\RoleAdministration;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Functions in the settings (right roles.manage, checked by @can). The
 * rules are in App\Services\RoleAdministration.
 */
class ManageRoleFunctions
{
    use ResolvesViewer;

    public function __construct(private readonly RoleAdministration $administration) {}

    /**
     * @param  array{input: array{scope: string, names: array{de: string, en: string}, rights: list<string>}}  $args
     */
    public function create(mixed $root, array $args, GraphQLContext $context): RoleFunction
    {
        $input = $args['input'];

        return $this->administration->createFunction($this->viewer($context), $input['scope'], $input['names'], $input['rights']);
    }

    /**
     * @param  array{id: int|string, input: array<string, mixed>}  $args
     */
    public function update(mixed $root, array $args, GraphQLContext $context): RoleFunction
    {
        return $this->administration->updateFunction($this->viewer($context), $args['id'], $args['input']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function duplicate(mixed $root, array $args, GraphQLContext $context): RoleFunction
    {
        return $this->administration->duplicateFunction($this->viewer($context), $args['id']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function delete(mixed $root, array $args, GraphQLContext $context): RoleFunction
    {
        return $this->administration->deleteFunction($this->viewer($context), $args['id']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function reset(mixed $root, array $args, GraphQLContext $context): RoleFunction
    {
        return $this->administration->resetFunction($this->viewer($context), $args['id']);
    }
}
