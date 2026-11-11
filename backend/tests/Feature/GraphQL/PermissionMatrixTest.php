<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectAbility;
use App\Enums\SystemAbility;

/**
 * The rights table: only for users who manage users, and it shows the roles
 * the policies really use.
 */
class PermissionMatrixTest extends GraphQLTestCase
{
    private const QUERY = '{ permissionMatrix { system { ability roles } project { ability roles } } }';

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
            ->keyBy('ability');

        $this->assertSame(['ADMIN'], $system['USERS_MANAGE']['roles']);
        $this->assertEqualsCanonicalizing(['ADMIN', 'PROJEKTLEITUNG', 'KOORDINATION'], $system['PROJECTS_CREATE']['roles']);
        $this->assertEqualsCanonicalizing(['ADMIN', 'PROJEKTLEITUNG'], $system['LABEL_SETS_MANAGE']['roles']);
        $this->assertSame(['ADMIN'], $system['PROJECTS_MANAGE_ANY']['roles']);
    }

    public function test_admin_sees_the_roles_of_each_project_ability(): void
    {
        $project = collect($this->as('admin')->gql(self::QUERY)->json('data.permissionMatrix.project'))
            ->keyBy('ability');

        $this->assertEqualsCanonicalizing(['OWNER', 'MANAGER'], $project['MEMBERS_MANAGE']['roles']);
        $this->assertSame(['OWNER'], $project['PROJECT_DELETE']['roles']);
        $this->assertEqualsCanonicalizing(['OWNER', 'MANAGER', 'MEMBER'], $project['TODO_CREATE']['roles']);
    }

    public function test_every_ability_appears_once(): void
    {
        $matrix = $this->as('admin')->gql(self::QUERY)->json('data.permissionMatrix');

        $this->assertCount(count(SystemAbility::cases()), $matrix['system']);
        $this->assertCount(count(ProjectAbility::cases()), $matrix['project']);
    }
}
