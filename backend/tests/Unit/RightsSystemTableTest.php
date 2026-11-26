<?php

namespace Tests\Unit;

use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checks the standard system rights against the matrix of
 * docs/rollen-funktionen-rechte.md, §5. Any change of the standard must
 * change this matrix too.
 */
class RightsSystemTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, list<string>>
     */
    private const CONFIRMED = [
        'admin' => [
            'projects.create', 'projects.viewAll', 'projects.manageAny', 'users.manage', 'roles.manage',
            'templates.manage', 'templates.manageAny',
            'assistant.use', 'ideas.create', 'ideas.viewAny', 'settings.manage', 'system.view', 'database.view',
            'billing.manage',
        ],
        'projektleitung' => ['projects.create', 'templates.manage', 'assistant.use', 'ideas.create', 'system.view'],
        'koordination' => ['projects.create', 'assistant.use', 'ideas.create', 'system.view'],
        'mitglied' => ['assistant.use', 'ideas.create', 'system.view'],
        'lesend' => ['system.view'],
    ];

    /**
     * @return iterable<string, array{UserRole, Right, bool}>
     */
    public static function matrix(): iterable
    {
        foreach (UserRole::cases() as $role) {
            foreach (Right::ofScope(Right::SCOPE_SYSTEM) as $ability) {
                yield "{$ability->value} as {$role->value}" => [
                    $role,
                    $ability,
                    in_array($ability->value, self::CONFIRMED[$role->value], true),
                ];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_confirmed_abilities(UserRole $role, Right $ability, bool $expected): void
    {
        $this->assertSame($expected, Rights::allows(new User(['role' => $role]), $ability));
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
        $this->assertFalse(Rights::allows(null, Right::PROJECTS_CREATE));
    }

    public function test_only_the_lesend_role_has_an_empty_project_cap(): void
    {
        $cap = fn (string $role) => json_decode((string) DB::table('roles')->where(['scope' => 'system', 'key' => $role])->value('project_cap'), true);

        $this->assertSame([], $cap('lesend'));
        $this->assertNull($cap('admin'));
    }
}
