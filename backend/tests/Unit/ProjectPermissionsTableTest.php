<?php

namespace Tests\Unit;

use App\Enums\ItemStatus;
use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Support\ProjectPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the permission table against the matrix confirmed by the client
 * (docs/graphql-kontext.md, "Rechte-Tabelle"). Order: owner, manager, member.
 */
class ProjectPermissionsTableTest extends TestCase
{
    private const CONFIRMED_MATRIX = [
        'project.update' => [true, true, false],
        'project.delete' => [true, false, false],
        'members.manage' => [true, true, false],
        'milestone.create' => [true, true, true],
        'milestone.update' => [true, true, true],
        'milestone.delete' => [true, true, false],
        'todo.create' => [true, true, true],
        'todo.update' => [true, true, true],
        'todo.delete' => [true, true, false],
        'task.create' => [true, true, false],
        'task.update' => [true, true, false],
        'task.assign' => [true, true, false],
        'task.move' => [true, true, true],
        'task.delete' => [true, true, false],
        'comment.create' => [true, true, true],
        'comment.moderate' => [true, true, false],
    ];

    /**
     * @return iterable<string, array{ProjectAbility, ProjectRole, bool}>
     */
    public static function matrix(): iterable
    {
        $roles = [ProjectRole::Owner, ProjectRole::Manager, ProjectRole::Member];

        foreach (self::CONFIRMED_MATRIX as $ability => $allowed) {
            foreach ($roles as $i => $role) {
                yield "{$ability} as {$role->value}" => [ProjectAbility::from($ability), $role, $allowed[$i]];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_role_has_exactly_the_confirmed_abilities(ProjectAbility $ability, ProjectRole $role, bool $expected): void
    {
        $this->assertSame($expected, ProjectPermissions::roleAllows($role, $ability));
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
            $this->assertFalse(ProjectPermissions::roleAllows(null, $ability));
        }

        $this->assertSame([], ProjectPermissions::abilitiesOfRole(null));
    }

    public function test_abilities_of_member(): void
    {
        $this->assertSame([
            ProjectAbility::MilestoneCreate,
            ProjectAbility::MilestoneUpdate,
            ProjectAbility::TodoCreate,
            ProjectAbility::TodoUpdate,
            ProjectAbility::TaskMove,
            ProjectAbility::CommentCreate,
        ], ProjectPermissions::abilitiesOfRole(ProjectRole::Member));
    }

    public function test_php_enums_match_graphql_enums(): void
    {
        $schema = file_get_contents(__DIR__.'/../../graphql/schema.graphql');

        $this->assertEqualsCanonicalizing(
            $this->graphqlEnumValues($schema, 'ProjectAbility'),
            array_map(fn (ProjectAbility $a) => $a->value, ProjectAbility::cases()),
        );
        $this->assertEqualsCanonicalizing(
            $this->graphqlEnumValues($schema, 'ProjectRole'),
            array_map(fn (ProjectRole $r) => $r->value, ProjectRole::cases()),
        );
        $this->assertEqualsCanonicalizing(
            $this->graphqlEnumValues($schema, 'ItemStatus'),
            array_map(fn (ItemStatus $s) => $s->value, ItemStatus::cases()),
        );
        $this->assertEqualsCanonicalizing(
            $this->graphqlEnumValues($schema, 'TaskStatus'),
            array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases()),
        );
    }

    /**
     * @return list<string>
     */
    private function graphqlEnumValues(string $schema, string $enum): array
    {
        $this->assertSame(1, preg_match('/enum '.$enum.' \{(.*?)\}/s', $schema, $block), "Enum {$enum} not found.");
        preg_match_all('/@enum\(value: "([^"]+)"\)/', $block[1], $values);

        return $values[1];
    }
}
