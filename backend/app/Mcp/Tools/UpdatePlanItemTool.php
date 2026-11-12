<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLFailed;
use App\Mcp\Support\PlainTextDocument;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_plan_item')]
#[Description('Changes the title, description or due date of a milestone or a todo. Only the fields you give are changed; the others stay as they are. The status is never set here: the app calculates it from the tasks. Nothing is deleted, and no e-mails are sent.')]
class UpdatePlanItemTool extends Tool
{
    use ExplainsGraphQLErrors, RunsGraphQL;

    private const MUTATIONS = [
        'milestone' => 'mutation ($id: ID!, $input: UpdateMilestoneInput!) { updateMilestone(id: $id, input: $input) { id title dueDate } }',
        'todo' => 'mutation ($id: ID!, $input: UpdateTodoInput!) { updateTodo(id: $id, input: $input) { id title dueDate } }',
    ];

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['milestone', 'todo'])],
            'id' => ['required'],
            'title' => ['nullable', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'type.in' => 'The type is milestone or todo.',
            'title.min' => 'A title needs at least 2 characters.',
            'due_date.date_format' => 'Dates are YYYY-MM-DD, for example 2027-03-31.',
        ]);

        // Only the given keys go into the input; a missing key leaves the field alone.
        $input = [];

        if ($request->has('title')) {
            $input['title'] = $validated['title'];
        }
        if ($request->has('description')) {
            $input['description'] = PlainTextDocument::from($validated['description'] ?? null);
        }
        if ($request->has('due_date')) {
            $input['dueDate'] = $validated['due_date'];
        }

        if ($input === []) {
            return Response::error('Give at least one field to change: title, description or due_date. Nothing was changed.');
        }

        try {
            $item = $this->graphQL(self::MUTATIONS[$validated['type']], [
                'id' => (string) $validated['id'],
                'input' => $input,
            ])[$validated['type'] === 'milestone' ? 'updateMilestone' : 'updateTodo'];
        } catch (GraphQLFailed $failure) {
            return Response::error($this->explain($failure->getMessage(), 'You may not change this item. Nothing was changed.', 'This milestone or todo does not exist, or you have no access to it. Nothing was changed.'));
        }

        return Response::json(['type' => $validated['type'], 'id' => $item['id'], 'title' => $item['title'], 'due_date' => $item['dueDate']]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['milestone', 'todo'])->description('milestone or todo.')->required(),
            'id' => $schema->string()->description('Id of the milestone or todo (from get_project or create_project_plan).')->required(),
            'title' => $schema->string()->description('New title, at least 2 characters.'),
            'description' => $schema->string()->description('New description, plain text. Empty text clears it.'),
            'due_date' => $schema->string()->format('date')->description('New due date, YYYY-MM-DD. Null clears it.'),
        ];
    }
}
