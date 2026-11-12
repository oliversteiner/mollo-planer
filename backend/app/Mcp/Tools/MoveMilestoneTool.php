<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLFailed;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('move_milestone')]
#[Description('Moves a milestone to another place in its project, for example to put a version in order. Position 0 is the first. The other milestones keep their order and move along. Only the order changes; titles, todos and tasks stay as they are.')]
class MoveMilestoneTool extends Tool
{
    use ExplainsGraphQLErrors, RunsGraphQL;

    private const MUTATION = 'mutation ($id: ID!, $position: Int!) { moveMilestone(id: $id, position: $position) { id title position } }';

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'milestone_id' => ['required'],
            'position' => ['required', 'integer', 'min:0'],
        ], [
            'milestone_id.required' => 'Give the milestone_id, for example from get_project.',
            'position.required' => 'Give the position, 0 for the first place.',
            'position.min' => 'The position starts at 0.',
        ]);

        try {
            $milestone = $this->graphQL(self::MUTATION, [
                'id' => (string) $validated['milestone_id'],
                'position' => (int) $validated['position'],
            ])['moveMilestone'];
        } catch (GraphQLFailed $failure) {
            return Response::error($this->explain($failure->getMessage(), 'You may not move this milestone. Nothing was changed.', 'This milestone does not exist, or you have no access to it. Nothing was changed.'));
        }

        return Response::json(['id' => $milestone['id'], 'title' => $milestone['title'], 'position' => $milestone['position']]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'milestone_id' => $schema->string()->description('Id of the milestone (from get_project).')->required(),
            'position' => $schema->integer()->description('New place, 0 is the first.')->required(),
        ];
    }
}
