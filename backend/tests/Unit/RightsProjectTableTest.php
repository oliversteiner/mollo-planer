<?php

namespace Tests\Unit;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Support\Rights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checks the permission table against the matrix confirmed by the client
 * (docs/graphql-kontext.md, "Rechte-Tabelle"). Order: owner, manager, member.
 */
class RightsProjectTableTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMED_MATRIX = [
        'project.update' => [true, true, false, false],
        'project.delete' => [true, false, false, false],
        'members.manage' => [true, true, false, false],
        'milestone.create' => [true, true, true, false],
        'milestone.update' => [true, true, true, false],
        'milestone.delete' => [true, true, false, false],
        'todo.create' => [true, true, true, false],
        'todo.update' => [true, true, true, false],
        'todo.delete' => [true, true, false, false],
        'task.create' => [true, true, true, false],
        'task.update' => [true, true, false, false],
        'task.assign' => [true, true, false, false],
        'task.move' => [true, true, true, false],
        'task.delete' => [true, true, false, false],
        'comment.create' => [true, true, true, false],
        'comment.moderate' => [true, true, false, false],
        'task.updateOwn' => [true, true, true, false],
        'task.assignSelf' => [true, true, true, false],
        'owner.manage' => [true, false, false, false],
    ];

    /**
     * @return iterable<string, array{ProjectAbility, ProjectRole, bool}>
     */
    public static function matrix(): iterable
    {
        $roles = [ProjectRole::OWNER, ProjectRole::MANAGER, ProjectRole::MEMBER, ProjectRole::GUEST];

        foreach (self::CONFIRMED_MATRIX as $ability => $allowed) {
            foreach ($roles as $i => $role) {
                yield "{$ability} as {$role->value}" => [ProjectAbility::from($ability), $role, $allowed[$i]];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_confirmed_abilities(ProjectAbility $ability, ProjectRole $role, bool $expected): void
    {
        $this->assertSame($expected, Rights::projectRoleAllows($role, $ability));
    }

    public function test_matrix_covers_every_ability(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(self::CONFIRMED_MATRIX),
            array_map(fn (ProjectAbility $a) => $a->value, ProjectAbility::cases()),
        );
    }

    public function test_without_role_nothing_is_allowed(): void
    {
        foreach (ProjectAbility::cases() as $ability) {
            $this->assertFalse(Rights::projectRoleAllows(null, $ability));
        }

        $this->assertSame([], Rights::projectRightsOfRole(null));
    }

    public function test_abilities_of_member(): void
    {
        $this->assertSame([
            ProjectAbility::MILESTONE_CREATE,
            ProjectAbility::MILESTONE_UPDATE,
            ProjectAbility::TODO_CREATE,
            ProjectAbility::TODO_UPDATE,
            ProjectAbility::TASK_CREATE,
            ProjectAbility::TASK_MOVE,
            ProjectAbility::TASK_UPDATE_OWN,
            ProjectAbility::TASK_ASSIGN_SELF,
            ProjectAbility::COMMENT_CREATE,
        ], Rights::projectRightsOfRole(ProjectRole::MEMBER));
    }
}
