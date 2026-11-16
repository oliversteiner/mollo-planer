<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Testing\TestResponse;

/**
 * Members right when a project is created (docs/fix-m1-mitglieder.md §1.2).
 */
class CreateProjectMembersTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($in: CreateProjectInput!) { createProject(input: $in) { id } }';

    /**
     * @param  list<array{userId: int|string, role: string}>  $members
     */
    private function create(string $as, array $members): TestResponse
    {
        return $this->as($as)->gql(self::MUTATION, ['in' => ['name' => 'Neu mit Team', 'members' => $members]]);
    }

    private function roleIn(Project $project, User $user): ?string
    {
        return $project->roleOf($user)?->key;
    }

    public function test_members_get_their_roles_and_the_creator_keeps_the_creator_role(): void
    {
        $id = $this->create('projektleitung', [
            ['userId' => $this->users['member']->id, 'role' => 'member'],
            ['userId' => $this->users['stranger']->id, 'role' => 'manager'],
            ['userId' => $this->users['projektleitung']->id, 'role' => 'guest'],
        ])->assertJsonMissingPath('errors')->json('data.createProject.id');

        $project = Project::findOrFail($id);
        $this->assertSame('member', $this->roleIn($project, $this->users['member']));
        $this->assertSame('manager', $this->roleIn($project, $this->users['stranger']));
        $this->assertSame('owner', $this->roleIn($project, $this->users['projektleitung']));
        $this->assertSame(1, $project->fresh()->planer_revision, 'creating with members is one change');
    }

    public function test_an_admin_in_the_members_is_there_once_with_the_creator_role(): void
    {
        $id = $this->create('admin', [
            ['userId' => $this->users['admin']->id, 'role' => 'guest'],
            ['userId' => $this->users['member']->id, 'role' => 'member'],
        ])->assertJsonMissingPath('errors')->json('data.createProject.id');

        $project = Project::findOrFail($id);
        $this->assertSame('owner', $this->roleIn($project, $this->users['admin']));
        $this->assertSame(2, $project->members()->count());
    }

    public function test_a_person_twice_is_refused_and_nothing_is_created(): void
    {
        $before = Project::count();

        $this->create('projektleitung', [
            ['userId' => $this->users['member']->id, 'role' => 'member'],
            ['userId' => $this->users['member']->id, 'role' => 'manager'],
        ])->assertGraphQLValidationKeys(['input.members.1.userId']);

        $this->assertSame($before, Project::count());
    }

    public function test_a_person_who_is_not_approved_is_refused(): void
    {
        $pending = User::factory()->unapproved()->create();

        $this->create('projektleitung', [['userId' => $pending->id, 'role' => 'member']])
            ->assertGraphQLValidationKeys(['input.members.0.userId']);
    }

    public function test_an_unknown_role_is_refused_at_its_row(): void
    {
        $this->create('projektleitung', [['userId' => $this->users['member']->id, 'role' => 'gibt-es-nicht']])
            ->assertGraphQLValidationKeys(['input.members.0.role']);
    }

    public function test_the_ground_rule_on_the_owner_role_holds(): void
    {
        // A creator role without owner.manage may not hand out a role with "delete the project".
        AppSettings::setProjectCreatorRole(Role::resolveOrFail(Role::SCOPE_PROJECT, 'manager'));

        $this->create('projektleitung', [['userId' => $this->users['member']->id, 'role' => 'owner']])
            ->assertGraphQLValidationKeys(['input.members.0.role']);

        $this->create('projektleitung', [['userId' => $this->users['member']->id, 'role' => 'member']])
            ->assertJsonMissingPath('errors');
    }

    public function test_without_members_the_project_starts_at_revision_zero(): void
    {
        $id = $this->create('projektleitung', [])->json('data.createProject.id');

        $this->assertSame(0, Project::findOrFail($id)->planer_revision);
    }
}
