<?php

namespace Tests\Feature\GraphQL;

use App\Enums\Right;

/**
 * The rights table: only for users who manage users, and it shows the roles
 * the policies really use.
 */
class PermissionMatrixTest extends GraphQLTestCase
{
    private const QUERY = '{ permissionMatrix { system { right roles { key } } project { right roles { key } } } }';

    public function test_only_user_managers_see_the_rights_table(): void
    {
        $this->assertUnauthorized($this->as('projektleitung')->gql(self::QUERY));
        $this->assertUnauthorized($this->as('manager')->gql(self::QUERY));
        $this->assertUnauthorized($this->as('member')->gql(self::QUERY));
        $this->assertUnauthorized($this->as('lesend')->gql(self::QUERY));
    }

    public function test_admin_sees_the_roles_of_each_system_ability(): void
    {
        $system = collect($this->as('admin')->gql(self::QUERY)->json('data.permissionMatrix.system'))
            ->mapWithKeys(fn (array $row): array => [$row['right'] => array_column($row['roles'], 'key')]);

        $this->assertSame(['admin'], $system['USERS_MANAGE']);
        $this->assertEqualsCanonicalizing(['admin', 'projektleitung', 'koordination'], $system['PROJECTS_CREATE']);
        $this->assertEqualsCanonicalizing(['admin', 'projektleitung'], $system['TEMPLATES_MANAGE']);
        $this->assertSame(['admin'], $system['PROJECTS_MANAGE_ANY']);
    }

    public function test_admin_sees_the_roles_of_each_project_ability(): void
    {
        $project = collect($this->as('admin')->gql(self::QUERY)->json('data.permissionMatrix.project'))
            ->mapWithKeys(fn (array $row): array => [$row['right'] => array_column($row['roles'], 'key')]);

        $this->assertEqualsCanonicalizing(['owner', 'manager'], $project['MEMBERS_MANAGE']);
        $this->assertSame(['owner'], $project['PROJECT_DELETE']);
        $this->assertEqualsCanonicalizing(['owner', 'manager', 'member'], $project['TODO_CREATE']);
    }

    public function test_every_ability_appears_once(): void
    {
        $matrix = $this->as('admin')->gql(self::QUERY)->json('data.permissionMatrix');

        $this->assertCount(count(Right::ofScope(Right::SCOPE_SYSTEM)), $matrix['system']);
        $this->assertCount(count(Right::ofScope(Right::SCOPE_PROJECT)), $matrix['project']);
    }
}
