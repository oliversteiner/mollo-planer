<?php

namespace Tests\Feature\GraphQL;

/**
 * A missing right is refused in the language of the request
 * (docs/fix-m2.md F5.5); refusals with their own sentence stay as they are.
 */
class RefusalMessageTest extends GraphQLTestCase
{
    private const ARCHIVE = 'mutation ($id: ID!) { archiveProject(id: $id) { id } }';

    public function test_a_missing_right_is_refused_in_german(): void
    {
        $this->withHeader('Accept-Language', 'de')->as('member')->gql(self::ARCHIVE, ['id' => $this->project->id])
            ->assertGraphQLErrorMessage('Dafür fehlt dir die Berechtigung.');
    }

    public function test_a_missing_right_is_refused_in_english(): void
    {
        $this->withHeader('Accept-Language', 'en')->as('member')->gql(self::ARCHIVE, ['id' => $this->project->id])
            ->assertGraphQLErrorMessage('You are not allowed to do this.');
    }

    public function test_a_refusal_with_its_own_sentence_keeps_it(): void
    {
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->withHeader('Accept-Language', 'de')->as('owner')
            ->gql('mutation ($id: ID!) { updateProject(id: $id, input: { name: "Neu" }) { id } }', ['id' => $this->project->id])
            ->assertGraphQLErrorMessage(__('projects.archived', [], 'de'));
    }
}
