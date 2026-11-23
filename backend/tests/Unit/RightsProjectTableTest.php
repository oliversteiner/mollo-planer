<?php

namespace Tests\Unit;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Support\Rights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Checks the standard rights against the matrix of
 * docs/rollen-funktionen-rechte.md, §5. Order: owner, manager, member, guest.
 */
class RightsProjectTableTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMED_MATRIX = [
        'project.update' => [true, true, false, false],
        'project.delete' => [true, false, false, false],
        'project.archive' => [true, true, false, false],
        'members.manage' => [true, true, false, false],
        'owner.manage' => [true, false, false, false],
        'milestone.create' => [true, true, true, false],
        'milestone.update' => [true, true, true, false],
        'milestone.delete' => [true, true, false, false],
        'todo.create' => [true, true, true, false],
        'todo.update' => [true, true, true, false],
        'todo.delete' => [true, true, false, false],
        'task.create' => [true, true, true, false],
        'task.update' => [true, true, false, false],
        'task.updateOwn' => [true, true, true, false],
        'task.assign' => [true, true, false, false],
        'task.assignSelf' => [true, true, true, false],
        'task.move' => [true, true, true, false],
        'task.delete' => [true, true, false, false],
        'comment.create' => [true, true, true, false],
        'comment.moderate' => [true, true, false, false],
        'trash.restore' => [true, true, false, false],
        'trash.purge' => [true, false, false, false],
        'planer.export' => [true, true, true, false],
        'planer.import' => [true, true, false, false],
        'aiContext.update' => [true, true, false, false],
        'document.create' => [true, true, true, false],
        'document.update' => [true, true, true, false],
        'document.manageAny' => [true, true, false, false],
    ];

    /**
     * @return iterable<string, array{Right, ProjectRole, bool}>
     */
    public static function matrix(): iterable
    {
        $roles = [ProjectRole::OWNER, ProjectRole::MANAGER, ProjectRole::MEMBER, ProjectRole::GUEST];

        foreach (self::CONFIRMED_MATRIX as $ability => $allowed) {
            foreach ($roles as $i => $role) {
                yield "{$ability} as {$role->value}" => [Right::from($ability), $role, $allowed[$i]];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_confirmed_abilities(Right $ability, ProjectRole $role, bool $expected): void
    {
        $this->assertSame($expected, Rights::projectRoleAllows($this->projectRole($role), $ability));
    }

    public function test_matrix_covers_every_ability(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(self::CONFIRMED_MATRIX),
            array_map(fn (Right $a) => $a->value, Right::ofScope(Right::SCOPE_PROJECT)),
        );
    }

    public function test_without_role_nothing_is_allowed(): void
    {
        foreach (Right::ofScope(Right::SCOPE_PROJECT) as $ability) {
            $this->assertFalse(Rights::projectRoleAllows(null, $ability));
        }

        $this->assertSame([], Rights::projectRightsOfRole(null));
    }

    public function test_abilities_of_member(): void
    {
        $this->assertSame([
            Right::MILESTONE_CREATE,
            Right::MILESTONE_UPDATE,
            Right::TODO_CREATE,
            Right::TODO_UPDATE,
            Right::TASK_CREATE,
            Right::TASK_UPDATE_OWN,
            Right::TASK_ASSIGN_SELF,
            Right::TASK_MOVE,
            Right::COMMENT_CREATE,
            Right::PLANER_EXPORT,
            Right::DOCUMENT_CREATE,
            Right::DOCUMENT_UPDATE,
        ], Rights::projectRightsOfRole($this->projectRole(ProjectRole::MEMBER)));
    }
}
