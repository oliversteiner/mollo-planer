<?php

namespace Tests\Feature\GraphQL;

use App\Models\Task;
use App\Models\Todo;
use App\Services\Deletion;
use PHPUnit\Framework\Attributes\Group;

/**
 * The order of the tasks of a todo (docs/auftrag-m4.md §2.3), apart from the
 * Kanban column: new tasks go to the end, deleting closes the gap, coming back
 * from the trash goes to the end, moveTaskInTodo and moveTaskToTodo renumber.
 */
#[Group('mariadb')]
class TaskOrderTest extends GraphQLTestCase
{
    private const MOVE = 'mutation ($id: ID!, $p: Int!) { moveTaskInTodo(id: $id, position: $p) { id position boardPosition status } }';

    /** @return list<int> */
    private function positions(Todo $todo): array
    {
        return $todo->tasks()->pluck('position')->map(fn ($p) => (int) $p)->all();
    }

    public function test_a_new_task_goes_to_the_end_of_its_todo(): void
    {
        $second = Task::factory()->for($this->todo)->create();

        $this->assertSame([$this->task->id, $second->id], $this->todo->tasks()->pluck('id')->all());
        $this->assertSame([0, 1], $this->positions($this->todo));
    }

    public function test_the_todo_lists_its_tasks_by_position(): void
    {
        $second = Task::factory()->for($this->todo)->create();
        $this->as('member')->gql(self::MOVE, ['id' => $second->id, 'p' => 0])->assertJsonMissingPath('errors');

        $this->as('member')->gql('query ($id: ID!) { project(id: $id) { milestones { todos { tasks { id position } } } } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.milestones.0.todos.0.tasks.0.id', (string) $second->id)
            ->assertJsonPath('data.project.milestones.0.todos.0.tasks.1.position', 1);
    }

    public function test_moving_in_the_todo_renumbers_and_keeps_status_and_board_place(): void
    {
        $second = Task::factory()->for($this->todo)->create(['board_position' => 1]);
        $third = Task::factory()->for($this->todo)->create(['board_position' => 2]);

        $this->as('member')->gql(self::MOVE, ['id' => $third->id, 'p' => 0])
            ->assertJsonPath('data.moveTaskInTodo.position', 0)
            ->assertJsonPath('data.moveTaskInTodo.boardPosition', 2)
            ->assertJsonPath('data.moveTaskInTodo.status', 'OPEN');

        $this->assertSame([$third->id, $this->task->id, $second->id], $this->todo->tasks()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $this->positions($this->todo));
        $this->assertSame(1, (int) $second->fresh()->board_position);
    }

    public function test_a_place_beyond_the_end_puts_the_task_last(): void
    {
        $second = Task::factory()->for($this->todo)->create();

        $this->as('member')->gql(self::MOVE, ['id' => $this->task->id, 'p' => 99])->assertJsonPath('data.moveTaskInTodo.position', 1);

        $this->assertSame([$second->id, $this->task->id], $this->todo->tasks()->pluck('id')->all());
    }

    public function test_deleting_a_task_closes_the_gap_and_restoring_puts_it_last(): void
    {
        $this->actingAs($this->users['owner']);
        $second = Task::factory()->for($this->todo)->create();
        $third = Task::factory()->for($this->todo)->create();

        app(Deletion::class)->delete($second);

        $this->assertSame([0, 1], $this->positions($this->todo));
        $this->assertSame(1, (int) $third->fresh()->position);

        app(Deletion::class)->restore($second->fresh()->deletion_id, $this->users['owner']);

        $this->assertSame([$this->task->id, $third->id, $second->id], $this->todo->tasks()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $this->positions($this->todo));
    }

    public function test_moving_to_another_todo_goes_to_the_end_and_closes_the_old_gap(): void
    {
        $other = Todo::factory()->for($this->milestone)->create();
        $otherTask = Task::factory()->for($other)->create();
        $stays = Task::factory()->for($this->todo)->create();

        $this->as('manager')
            ->gql('mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { position } }', ['id' => $this->task->id, 't' => $other->id])
            ->assertJsonPath('data.moveTaskToTodo.position', 1);

        $this->assertSame([$otherTask->id, $this->task->id], $other->tasks()->pluck('id')->all());
        $this->assertSame([$stays->id], $this->todo->tasks()->pluck('id')->all());
        $this->assertSame([0], $this->positions($this->todo));
    }

    public function test_a_guest_may_not_reorder(): void
    {
        Task::factory()->for($this->todo)->create();

        $this->as('lesend')->gql(self::MOVE, ['id' => $this->task->id, 'p' => 1])->assertGraphQLErrorMessage('You are not allowed to do this.');
    }
}
