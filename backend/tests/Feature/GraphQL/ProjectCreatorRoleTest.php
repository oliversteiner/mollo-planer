<?php

namespace Tests\Feature\GraphQL;

use App\Support\AppSettings;

/**
 * The project role of the creator of a project (docs/rollen-funktionen-rechte.md,
 * §7.3 and the settings).
 */
class ProjectCreatorRoleTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($key: String!) { setProjectCreatorRole(roleKey: $key) { projectCreatorRole { key } } }';

    public function test_the_standard_creator_role_is_owner(): void
    {
        $this->as('admin')->gql('{ appSettings { projectCreatorRole { key } } }')
            ->assertJsonPath('data.appSettings.projectCreatorRole.key', 'owner');
    }

    public function test_an_admin_sets_a_role_with_project_delete(): void
    {
        $this->as('admin')->gql(self::MUTATION, ['key' => 'owner'])
            ->assertJsonMissingPath('errors');

        $this->assertSame('owner', AppSettings::projectCreatorRole()->key);
    }

    public function test_a_role_without_project_delete_is_refused(): void
    {
        $this->as('admin')->gql(self::MUTATION, ['key' => 'manager'])
            ->assertGraphQLValidationKeys(['roleKey']);

        $this->assertSame('owner', AppSettings::projectCreatorRole()->key);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->as('admin')->gql(self::MUTATION, ['key' => 'gibt-es-nicht'])
            ->assertGraphQLValidationKeys(['roleKey']);
    }

    public function test_only_people_who_may_change_settings_set_it(): void
    {
        $this->assertUnauthorized($this->as('projektleitung')->gql(self::MUTATION, ['key' => 'owner']));
    }
}
