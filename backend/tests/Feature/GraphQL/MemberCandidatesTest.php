<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

/**
 * People to take into a project (docs/fix-m1-mitglieder.md §1.1).
 */
class MemberCandidatesTest extends GraphQLTestCase
{
    private const QUERY = 'query ($p: ID) { memberCandidates(projectId: $p) { id name isMember } }';

    /** @return list<string> */
    private function names(string $as, ?int $projectId): array
    {
        return collect($this->as($as)->gql(self::QUERY, ['p' => $projectId])->json('data.memberCandidates'))->pluck('name')->all();
    }

    public function test_people_who_manage_members_in_the_project_see_everybody_with_their_membership(): void
    {
        $response = $this->as('manager')->gql(self::QUERY, ['p' => $this->project->id]);

        $people = collect($response->json('data.memberCandidates'))->keyBy('name');
        $this->assertSame(User::whereNotNull('approved_at')->count(), $people->count());
        $this->assertTrue($people['Ben Owner']['isMember']);
        $this->assertTrue($people['David Member']['isMember']);
        $this->assertFalse($people['Eva Stranger']['isMember']);
        $this->assertSame($people->keys()->sort()->values()->all(), $people->keys()->all(), 'sorted by name');
    }

    public function test_without_members_manage_in_this_project_the_list_is_empty(): void
    {
        $this->assertSame([], $this->names('member', $this->project->id));
        $this->assertSame([], $this->names('stranger', $this->project->id));

        // Managing members in another project is not enough.
        Project::factory()->withMember($this->users['stranger'], ProjectRole::OWNER)->create();
        $this->assertSame([], $this->names('stranger', $this->project->id));
    }

    public function test_without_a_project_it_needs_the_right_to_create_projects(): void
    {
        $this->assertContains('Eva Stranger', $this->names('projektleitung', null));
        $this->assertSame([], $this->names('member', null));
    }

    public function test_only_approved_people_are_offered_and_nobody_is_a_member_without_a_project(): void
    {
        User::factory()->unapproved()->create(['name' => 'Zora Pending']);

        $response = $this->as('admin')->gql(self::QUERY, ['p' => null]);

        $this->assertNotContains('Zora Pending', collect($response->json('data.memberCandidates'))->pluck('name'));
        $this->assertSame([false], collect($response->json('data.memberCandidates'))->pluck('isMember')->unique()->values()->all());
    }

    public function test_it_shows_no_e_mail_address(): void
    {
        $this->as('manager')->gql('query ($p: ID) { memberCandidates(projectId: $p) { email } }', ['p' => $this->project->id])
            ->assertGraphQLErrorMessage('Cannot query field "email" on type "MemberCandidate".');
    }
}
