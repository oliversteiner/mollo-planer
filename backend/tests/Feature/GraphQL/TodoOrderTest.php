<?php

namespace Tests\Feature\GraphQL;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Todo;

/**
 * The order of the todos of a milestone (schema-review A3): new todos go to the
 * end, a given place moves the others back, a deleted todo leaves no gap, and
 * moveTodo changes the place or the milestone inside the same project.
 */
class TodoOrderTest extends GraphQLTestCase
{
    private const MOVE = 'mutation ($id: ID!, $m: ID!, $p: Int!) { moveTodo(id: $id, milestoneId: $m, position: $p) { id position milestone { id } } }';

    private const CREATE = 'mutation ($in: CreateTodoInput!) { createTodo(input: $in) { id position } }';

    public function test_a_new_todo_goes_to_the_end_of_its_milestone(): void
    {
        $second = Todo::factory()->for($this->milestone)->create();

        $this->assertSame([$this->todo->id, $second->id], $this->milestone->todos()->pluck('id')->all());
        $this->assertSame([0, 1], $this->positions($this->milestone));
    }

    public function test_a_todo_at_a_position_moves_the_others_back(): void
    {
        $this->as('owner')->gql(self::CREATE, ['in' => ['milestoneId' => $this->milestone->id, 'title' => 'Erstes', 'position' => 0]])
            ->assertJsonPath('data.createTodo.position', 0);

        $this->assertSame([0, 1], $this->positions($this->milestone));
        $this->assertSame('Erstes', $this->milestone->todos()->first()->title);
    }

    public function test_deleting_a_todo_closes_the_gap(): void
    {
        $second = Todo::factory()->for($this->milestone)->create();
        $third = Todo::factory()->for($this->milestone)->create();

        $second->delete();

        $this->assertSame([0, 1], $this->positions($this->milestone));
        $this->assertSame(1, (int) $third->fresh()->position);
    }

    public function test_move_within_the_milestone_renumbers_the_order(): void
    {
        $second = Todo::factory()->for($this->milestone)->create();
        $third = Todo::factory()->for($this->milestone)->create();

        $this->as('owner')->gql(self::MOVE, ['id' => $third->id, 'm' => $this->milestone->id, 'p' => 0])->assertJsonMissingPath('errors');

        $this->assertSame([$third->id, $this->todo->id, $second->id], $this->milestone->todos()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $this->positions($this->milestone));
    }

    public function test_move_to_another_milestone_of_the_same_project(): void
    {
        $other = Milestone::factory()->for($this->project)->create();
        $otherTodo = Todo::factory()->for($other)->create();

        $this->as('member')->gql(self::MOVE, ['id' => $this->todo->id, 'm' => $other->id, 'p' => 0])
            ->assertJsonPath('data.moveTodo.milestone.id', (string) $other->id);

        $this->assertSame([$this->todo->id, $otherTodo->id], $other->todos()->pluck('id')->all());
        $this->assertSame([0, 1], $this->positions($other));
        $this->assertSame([], $this->milestone->todos()->pluck('id')->all());
    }

    public function test_move_to_a_milestone_of_another_project_is_refused_and_changes_nothing(): void
    {
        $foreign = Milestone::factory()->for(Project::factory()->create())->create();
        $before = $this->todo->fresh()->toArray();

        $this->as('owner')->gql(self::MOVE, ['id' => $this->todo->id, 'm' => $foreign->id, 'p' => 0])
            ->assertGraphQLValidationKeys(['milestoneId']);

        $this->assertSame($before, $this->todo->fresh()->toArray());
    }

    /**
     * @return list<int>
     */
    private function positions(Milestone $milestone): array
    {
        return $milestone->todos()->pluck('position')->map(fn ($position) => (int) $position)->values()->all();
    }
}
