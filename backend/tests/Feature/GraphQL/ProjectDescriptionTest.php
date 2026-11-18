<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;

/**
 * The project description is rich text like the milestones' (schema-review
 * C2, docs/auftrag-m2.md §5). The plan of the assistant keeps plain text.
 */
class ProjectDescriptionTest extends GraphQLTestCase
{
    public function test_the_description_is_saved_and_read_as_a_document(): void
    {
        $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Wichtig', 'marks' => [['type' => 'bold']]]]]]];

        $this->as('owner')->gql('mutation ($id: ID!, $d: RichText) { updateProject(id: $id, input: {description: $d}) { description } }', ['id' => $this->project->id, 'd' => $doc])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateProject.description', $doc);

        $this->assertSame($doc, $this->project->fresh()->description);
    }

    public function test_html_or_unknown_nodes_are_refused(): void
    {
        $this->as('owner')->gql('mutation ($id: ID!, $d: RichText) { updateProject(id: $id, input: {description: $d}) { id } }', [
            'id' => $this->project->id,
            'd' => ['type' => 'doc', 'content' => [['type' => 'iframe']]],
        ])->assertGraphQLValidationKeys(['input.description']);
    }

    public function test_an_empty_document_clears_the_description(): void
    {
        $this->as('owner')->gql('mutation ($id: ID!, $d: RichText) { updateProject(id: $id, input: {description: $d}) { description } }', [
            'id' => $this->project->id,
            'd' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]],
        ])->assertJsonPath('data.updateProject.description', null);
    }
}
