<?php

namespace Tests\Unit;

use App\Enums\ProjectAbility;
use App\Enums\SystemAbility;
use Tests\TestCase;

/**
 * config/permissions.php is text. A typo there would silently grant nothing,
 * so every entry must name a real right.
 */
class RightsConfigTest extends TestCase
{
    public function test_every_configured_right_is_a_known_right(): void
    {
        $systemValues = array_column(SystemAbility::cases(), 'value');
        $projectValues = array_column(ProjectAbility::cases(), 'value');

        foreach ((array) config('permissions.system') as $role => $rights) {
            foreach ($rights as $right) {
                $this->assertTrue($right === '*' || in_array($right, $systemValues, true), "system.{$role}: {$right}");
            }
        }

        foreach ((array) config('permissions.project') as $role => $rights) {
            foreach ($rights as $right) {
                $this->assertContains($right, $projectValues, "project.{$role}: {$right}");
            }
        }
    }
}
