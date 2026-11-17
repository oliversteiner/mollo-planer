<?php

namespace Tests\Feature\GraphQL;

use App\Models\Milestone;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\PlanerLimits;
use App\Services\Deletion;
use App\Services\ProjectMembership;
use Illuminate\Validation\ValidationException;

/**
 * The app follows the planer file (docs/auftrag-m4.md §2.2): the limits of
 * spec/format-v2.md hold for every way in, so every project can be exported.
 */
class PlanerLimitsTest extends GraphQLTestCase
{
    public function test_the_thirty_first_milestone_is_refused(): void
    {
        Milestone::factory()->count(PlanerLimits::MILESTONES_PER_PROJECT - 1)->for($this->project)->create();

        $this->as('owner')
            ->gql('mutation ($in: CreateMilestoneInput!) { createMilestone(input: $in) { id } }', ['in' => ['projectId' => $this->project->id, 'title' => 'Zu viel']])
            ->assertGraphQLValidationError('limit', 'A project has at most 30 milestones.');

        $this->assertSame(30, $this->project->milestones()->count());
    }

    public function test_the_fifty_first_todo_is_refused(): void
    {
        Todo::factory()->count(PlanerLimits::TODOS_PER_MILESTONE - 1)->for($this->milestone)->create();

        $this->as('owner')
            ->gql('mutation ($in: CreateTodoInput!) { createTodo(input: $in) { id } }', ['in' => ['milestoneId' => $this->milestone->id, 'title' => 'Zu viel']])
            ->assertGraphQLValidationError('limit', 'A milestone has at most 50 to-dos.');
    }

    public function test_the_fifty_first_task_is_refused(): void
    {
        Task::factory()->count(PlanerLimits::TASKS_PER_TODO - 1)->for($this->todo)->create();

        $this->as('owner')
            ->gql('mutation ($in: CreateTaskInput!) { createTask(input: $in) { id } }', ['in' => ['todoId' => $this->todo->id, 'title' => 'Zu viel']])
            ->assertGraphQLValidationError('limit', 'A to-do has at most 50 tasks.');
    }

    public function test_the_trash_does_not_count(): void
    {
        Milestone::factory()->count(PlanerLimits::MILESTONES_PER_PROJECT - 1)->for($this->project)->create();
        Milestone::query()->where('project_id', $this->project->id)->latest('id')->first()->delete();

        $this->as('owner')
            ->gql('mutation ($in: CreateMilestoneInput!) { createMilestone(input: $in) { id } }', ['in' => ['projectId' => $this->project->id, 'title' => 'Passt']])
            ->assertJsonMissingPath('errors');
    }

    public function test_moving_a_todo_into_a_full_milestone_is_refused(): void
    {
        $full = Milestone::factory()->for($this->project)->create();
        Todo::factory()->count(PlanerLimits::TODOS_PER_MILESTONE)->for($full)->create();

        $this->as('owner')
            ->gql('mutation ($id: ID!, $m: ID!) { moveTodo(id: $id, milestoneId: $m, position: 0) { id } }', ['id' => $this->todo->id, 'm' => $full->id])
            ->assertGraphQLValidationError('limit', 'A milestone has at most 50 to-dos.');

        $this->assertSame($this->milestone->id, $this->todo->fresh()->milestone_id);
    }

    public function test_moving_a_todo_inside_a_full_milestone_is_fine(): void
    {
        Todo::factory()->count(PlanerLimits::TODOS_PER_MILESTONE - 1)->for($this->milestone)->create();

        $this->as('owner')
            ->gql('mutation ($id: ID!, $m: ID!) { moveTodo(id: $id, milestoneId: $m, position: 3) { id } }', ['id' => $this->todo->id, 'm' => $this->milestone->id])
            ->assertJsonMissingPath('errors');
    }

    public function test_moving_a_task_into_a_full_todo_is_refused(): void
    {
        $full = Todo::factory()->for($this->milestone)->create();
        Task::factory()->count(PlanerLimits::TASKS_PER_TODO)->for($full)->create();

        $this->as('owner')
            ->gql('mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { id } }', ['id' => $this->task->id, 't' => $full->id])
            ->assertGraphQLValidationError('limit', 'A to-do has at most 50 tasks.');
    }

    public function test_restoring_from_the_trash_may_not_break_a_limit(): void
    {
        $this->actingAs($this->users['owner']);
        Milestone::factory()->count(PlanerLimits::MILESTONES_PER_PROJECT - 1)->for($this->project)->create();
        $deletion = app(Deletion::class);
        $gone = Milestone::query()->where('project_id', $this->project->id)->latest('id')->first();
        $deletion->delete($gone);
        Milestone::factory()->for($this->project)->create();

        try {
            $deletion->restore($gone->deletion_id, $this->users['owner']);
            $this->fail('The restore should have been refused.');
        } catch (ValidationException $error) {
            $this->assertSame(__('projects.limit_milestones', ['max' => 30]), $error->errors()['limit'][0]);
        }

        $this->assertNotNull(Milestone::onlyTrashed()->find($gone->id));
    }

    public function test_the_fifty_first_member_is_refused(): void
    {
        $this->actingAs($this->users['owner']);
        $role = Role::defaultOf(Role::SCOPE_PROJECT);
        $membership = app(ProjectMembership::class);

        foreach (User::factory()->count(PlanerLimits::MEMBERS_PER_PROJECT - 3)->create() as $user) {
            $membership->set($this->users['owner'], $this->project, $user, $role);
        }

        $this->assertSame(50, $this->project->members()->count());

        $this->expectException(ValidationException::class);
        $membership->set($this->users['owner'], $this->project, $this->users['stranger'], $role);
    }

    public function test_a_description_is_limited_as_markdown(): void
    {
        $query = 'mutation ($id: ID!, $d: RichText) { updateTask(id: $id, input: {description: $d}) { id } }';

        $this->as('owner')->gql($query, ['id' => $this->task->id, 'd' => $this->doc(str_repeat('a', 5000))])->assertJsonMissingPath('errors');
        $this->as('owner')->gql($query, ['id' => $this->task->id, 'd' => $this->doc(str_repeat('a', 5001))])->assertGraphQLValidationKeys(['input.description']);
    }

    public function test_formatting_counts_toward_the_markdown_limit(): void
    {
        $bold = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', 4997), 'marks' => [['type' => 'bold']]]]]]];

        $this->as('owner')
            ->gql('mutation ($id: ID!, $d: RichText) { updateTask(id: $id, input: {description: $d}) { id } }', ['id' => $this->task->id, 'd' => $bold])
            ->assertGraphQLValidationKeys(['input.description']);
    }
}
