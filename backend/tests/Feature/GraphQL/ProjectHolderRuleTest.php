<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

/**
 * Every project keeps a member who may delete it, also when an admin acts
 * and also through the system role (its cap) or deleting the person
 * (docs/rollen-funktionen-rechte.md §7.3, docs/fix-m1-abnahme.md F3.4).
 * Removing the last owner: MutationTest; roles and functions:
 * RoleAdministrationTest.
 */
class ProjectHolderRuleTest extends GraphQLTestCase
{
    private const UPDATE_USER = 'mutation ($id: ID!, $role: String) { updateUser(id: $id, input: {role: $role}) { role { key } } }';

    private const DELETE_USER = 'mutation ($id: ID!) { deleteUser(id: $id) { id } }';

    private Project $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->second = Project::factory()->withMember($this->users['owner'], ProjectRole::OWNER)->create(['name' => 'Abnahme']);
    }

    public function test_an_admin_may_not_take_the_owner_role_of_the_last_owner(): void
    {
        $this->as('admin')->gql('mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "member") { id } }', [
            'p' => $this->project->id, 'u' => $this->users['owner']->id,
        ])->assertGraphQLValidationKeys(['role']);

        $this->assertSame('owner', $this->project->roleOf($this->users['owner'])?->key);
    }

    public function test_a_system_role_whose_cap_takes_project_delete_is_refused_and_names_the_projects(): void
    {
        $response = $this->as('admin')->gql(self::UPDATE_USER, ['id' => $this->users['owner']->id, 'role' => 'lesend']);

        $response->assertGraphQLValidationKeys(['input.role']);
        $this->assertSame(
            'Ben Owner is the only person with “Delete project” in Abnahme and Mollo Planer. Give the role to someone else there first.',
            $response->json('errors.0.extensions.validation')['input.role'][0],
        );
        $this->assertSame('mitglied', $this->users['owner']->fresh()->role->key);
    }

    public function test_the_system_role_changes_when_another_member_holds_the_projects(): void
    {
        $this->project->members()->updateExistingPivot($this->users['manager']->id, ['role_id' => $this->projectRole(ProjectRole::OWNER)->id]);
        $this->second->members()->attach($this->users['manager']->id, ['role_id' => $this->projectRole(ProjectRole::OWNER)->id]);

        $this->as('admin')->gql(self::UPDATE_USER, ['id' => $this->users['owner']->id, 'role' => 'lesend'])
            ->assertJsonPath('data.updateUser.role.key', 'lesend');
    }

    public function test_a_system_role_that_keeps_project_delete_is_no_problem(): void
    {
        $this->as('admin')->gql(self::UPDATE_USER, ['id' => $this->users['owner']->id, 'role' => 'projektleitung'])
            ->assertJsonPath('data.updateUser.role.key', 'projektleitung');
    }

    public function test_the_last_owner_cannot_be_deleted(): void
    {
        $this->as('admin')->gql(self::DELETE_USER, ['id' => $this->users['owner']->id])
            ->assertGraphQLValidationKeys(['id']);

        $this->assertNotNull(User::find($this->users['owner']->id));
    }

    public function test_a_person_who_holds_no_project_alone_can_be_deleted(): void
    {
        $this->as('admin')->gql(self::DELETE_USER, ['id' => $this->users['manager']->id])
            ->assertJsonMissingPath('errors');

        $this->assertNull(User::find($this->users['manager']->id));
    }
}
