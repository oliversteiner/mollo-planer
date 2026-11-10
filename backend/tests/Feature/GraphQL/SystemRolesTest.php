<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Project;

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
        $this->assertSame(ProjectRole::OWNER, $project->roleOf($this->users['projektleitung']));

        $this->as('projektleitung')->gql('{ projects { name } }')
            ->assertJsonPath('data.projects.0.name', 'Neues Projekt');
    }

    public function test_an_admin_creates_a_project_without_becoming_a_member(): void
    {
        $this->as('admin')->gql(self::CREATE)->assertJsonPath('data.createProject.name', 'Neues Projekt');

        $project = Project::query()->where('name', 'Neues Projekt')->firstOrFail();
        $this->assertNull($project->roleOf($this->users['admin']));
    }

    public function test_read_only_users_may_change_nothing_even_as_owner(): void
    {
        $this->project->members()->attach($this->users['lesend']->id, ['role' => ProjectRole::OWNER->value]);

        $this->assertFalse($this->users['lesend']->can('update', $this->project));
        $this->assertFalse($this->users['lesend']->can('createMilestone', $this->project));
        $this->as('lesend')->gql('{ project(id: '.$this->project->id.') { name viewerAbilities } }')
            ->assertJsonPath('data.project.name', 'FCA DB')
            ->assertJsonPath('data.project.viewerAbilities', []);
    }

    public function test_member_search_gives_names_only_to_member_managers(): void
    {
        $search = '{ userSearch(term: "Ben") { id name } }';

        $this->as('manager')->gql($search)->assertJsonPath('data.userSearch', [
            ['id' => (string) $this->users['owner']->id, 'name' => 'Ben Owner'],
        ]);
        $this->as('member')->gql($search)->assertJsonPath('data.userSearch', []);
        $this->as('projektleitung')->gql($search)->assertJsonPath('data.userSearch', []);
    }

    public function test_projektleitung_may_not_see_the_user_list(): void
    {
        $this->assertUnauthorized($this->as('projektleitung')->gql('{ users { name } }'));
    }
}
