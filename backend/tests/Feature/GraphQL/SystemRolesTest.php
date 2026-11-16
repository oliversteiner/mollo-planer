<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Role;
use App\Support\Rights;

/**
 * What the global roles change in practice: who creates projects, what a
 * read-only user may do, and who may search for people to add as members.
 */
class SystemRolesTest extends GraphQLTestCase
{
    private const CREATE = 'mutation { createProject(input: {name: "Neues Projekt"}) { id name } }';

    public function test_only_roles_with_projects_create_create_projects(): void
    {
        $this->assertUnauthorized($this->as('member')->gql(self::CREATE));
        $this->assertUnauthorized($this->as('manager')->gql(self::CREATE));
        $this->assertUnauthorized($this->as('lesend')->gql(self::CREATE));

        $this->as('projektleitung')->gql(self::CREATE)->assertJsonPath('data.createProject.name', 'Neues Projekt');
        $this->as('koordination')->gql(str_replace('Neues Projekt', 'Zweites Projekt', self::CREATE))
            ->assertJsonPath('data.createProject.name', 'Zweites Projekt');
    }

    public function test_a_creator_who_is_no_admin_becomes_owner_and_sees_the_project(): void
    {
        $this->as('projektleitung')->gql(self::CREATE);

        $project = Project::query()->where('name', 'Neues Projekt')->firstOrFail();
        $this->assertSame(ProjectRole::OWNER->value, $project->roleOf($this->users['projektleitung'])?->key);

        $this->as('projektleitung')->gql('{ projects { name } }')
            ->assertJsonPath('data.projects.0.name', 'Neues Projekt');
    }

    public function test_an_admin_creates_a_project_and_becomes_a_member_with_the_creator_role(): void
    {
        $this->as('admin')->gql(self::CREATE)->assertJsonPath('data.createProject.name', 'Neues Projekt');

        $project = Project::query()->where('name', 'Neues Projekt')->firstOrFail();
        $this->assertSame(ProjectRole::OWNER->value, $project->roleOf($this->users['admin'])?->key);
        $this->assertSame(1, Rights::countHolders($project));
    }

    public function test_read_only_users_may_change_nothing_even_as_owner(): void
    {
        $this->project->members()->attach($this->users['lesend']->id, ['role_id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::OWNER)]);

        $this->assertFalse($this->users['lesend']->can('update', $this->project));
        $this->assertFalse($this->users['lesend']->can('createMilestone', $this->project));
        $this->as('lesend')->gql('{ project(id: '.$this->project->id.') { name viewerRights } }')
            ->assertJsonPath('data.project.name', 'Mollo Planer')
            ->assertJsonPath('data.project.viewerRights', []);
    }

    public function test_projektleitung_may_not_see_the_user_list(): void
    {
        $this->assertUnauthorized($this->as('projektleitung')->gql('{ users { name } }'));
    }

    public function test_everybody_signed_in_reads_the_roles_for_the_selection(): void
    {
        $roles = $this->as('member')->gql('{ roles(scope: PROJECT) { key scope isDefault isStandard names { de en } rights } }')
            ->assertJsonMissingPath('errors')
            ->json('data.roles');

        $this->assertSame(['owner', 'manager', 'member', 'guest'], array_column($roles, 'key'));
        $this->assertSame([false, false, true, false], array_column($roles, 'isDefault'));
        $this->assertSame(['de' => 'Gast', 'en' => 'Guest'], $roles[3]['names']);
        $this->assertSame([], $roles[3]['rights']);
        $this->assertContains('PROJECT_DELETE', $roles[0]['rights']);

        $this->assertCount(9, $this->as('member')->gql('{ roles { key } }')->json('data.roles'));
    }

    public function test_an_unknown_role_key_is_a_validation_error(): void
    {
        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: {role: "king"}) { id } }', ['id' => $this->users['member']->id])
            ->assertGraphQLValidationKeys(['role']);

        $this->as('owner')->gql('mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "admin") { id } }', ['p' => $this->project->id, 'u' => $this->users['stranger']->id])
            ->assertGraphQLValidationKeys(['role']);
    }
}
