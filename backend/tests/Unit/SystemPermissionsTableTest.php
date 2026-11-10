<?php

namespace Tests\Unit;

use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\SystemPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the system permission table against the matrix confirmed for the
 * roles (rollen-plan.md). Any change of the table must change this matrix too.
 */
class SystemPermissionsTableTest extends TestCase
{
    /**
     * @var array<string, list<string>>
     */
    private const CONFIRMED = [
        'admin' => ['projects.create', 'projects.viewAll', 'projects.manageAny', 'users.manage', 'labelSets.manage', 'labelSets.manageAny'],
        'projektleitung' => ['projects.create', 'labelSets.manage'],
        'koordination' => ['projects.create'],
        'mitglied' => [],
        'lesend' => [],
    ];

    /**
     * @return iterable<string, array{UserRole, SystemAbility, bool}>
     */
    public static function matrix(): iterable
    {
        foreach (UserRole::cases() as $role) {
            foreach (SystemAbility::cases() as $ability) {
                yield "{$ability->value} as {$role->value}" => [
                    $role,
                    $ability,
                    in_array($ability->value, self::CONFIRMED[$role->value], true),
                ];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_confirmed_abilities(UserRole $role, SystemAbility $ability, bool $expected): void
    {
        $this->assertSame($expected, SystemPermissions::allows(new User(['role' => $role]), $ability));
    }

    public function test_matrix_covers_every_role(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
            array_keys(self::CONFIRMED),
        );
    }

    public function test_guests_have_no_system_ability(): void
    {
        $this->assertFalse(SystemPermissions::allows(null, SystemAbility::PROJECTS_CREATE));
    }

    public function test_read_only_is_only_the_lesend_role(): void
    {
        $this->assertTrue(SystemPermissions::isReadOnly(new User(['role' => UserRole::LESEND])));
        $this->assertFalse(SystemPermissions::isReadOnly(new User(['role' => UserRole::ADMIN])));
    }
}
