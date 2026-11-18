<?php

namespace Tests\Unit;

use App\Enums\Right;
use Tests\TestCase;

/**
 * config/permissions.php is text that seeds the tables. A typo there would
 * silently grant nothing, so every right must be a real right and every
 * function and role reference must exist.
 */
class RightsConfigTest extends TestCase
{
    public function test_every_configured_right_is_a_known_right_of_its_scope(): void
    {
        foreach ([Right::SCOPE_SYSTEM, Right::SCOPE_PROJECT] as $scope) {
            $values = array_column(Right::ofScope($scope), 'value');

            foreach ((array) config("permissions.functions.{$scope}") as $key => $function) {
                foreach ($function['rights'] as $right) {
                    $this->assertContains($right, $values, "functions.{$scope}.{$key}: {$right}");
                }
            }
        }
    }

    public function test_every_role_refers_to_a_configured_function_of_its_scope(): void
    {
        foreach (['system', 'project'] as $scope) {
            $functions = array_keys((array) config("permissions.functions.{$scope}"));
            foreach ((array) config("permissions.roles.{$scope}") as $role => $definition) {
                foreach ($definition['functions'] as $function) {
                    $this->assertContains($function, $functions, "roles.{$scope}.{$role}: {$function}");
                }
            }
        }
    }

    public function test_every_right_of_the_enum_is_granted_by_some_function(): void
    {
        $granted = array_merge(
            ...array_map(fn (array $function): array => $function['rights'], array_values((array) config('permissions.functions.system'))),
            ...array_map(fn (array $function): array => $function['rights'], array_values((array) config('permissions.functions.project'))),
        );

        foreach (Right::cases() as $right) {
            $this->assertContains($right->value, $granted, "{$right->value} is in no function");
        }
    }
}
