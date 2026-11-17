<?php

namespace Tests\Feature\GraphQL;

use App\Models\Label;
use App\Models\Milestone;
use App\Models\Todo;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The planer revision (docs/umsetzung.md S3c): every change that the planer
 * file shows counts once for its project, whatever it touches inside.
 */
class PlanerRevisionTest extends GraphQLTestCase
{
    private function revision(): int
    {
        return (int) DB::table('projects')->where('id', $this->project->id)->value('planer_revision');
    }

    /**
     * @return array<string, array{string, string, Closure(self): array<string, mixed>}>
     */
    public static function changes(): array
    {
        return [
            'updateProject' => ['manager', 'mutation ($id: ID!) { updateProject(id: $id, input: {name: "Neu"}) { id } }', fn (self $t) => ['id' => $t->project->id]],
            'createMilestone' => ['manager', 'mutation ($p: ID!) { createMilestone(input: {projectId: $p, title: "Neu"}) { id } }', fn (self $t) => ['p' => $t->project->id]],
            'updateMilestone' => ['manager', 'mutation ($id: ID!) { updateMilestone(id: $id, input: {title: "Neu"}) { id } }', fn (self $t) => ['id' => $t->milestone->id]],
            'moveMilestone' => ['manager', 'mutation ($id: ID!) { moveMilestone(id: $id, position: 0) { id } }', fn (self $t) => ['id' => Milestone::factory()->for($t->project)->create()->id]],
            'deleteMilestone with todos and tasks' => ['manager', 'mutation ($id: ID!) { deleteMilestone(id: $id) { id } }', fn (self $t) => ['id' => $t->milestone->id]],
            'createTodo' => ['manager', 'mutation ($m: ID!) { createTodo(input: {milestoneId: $m, title: "Neu"}) { id } }', fn (self $t) => ['m' => $t->milestone->id]],
            'updateTodo' => ['manager', 'mutation ($id: ID!) { updateTodo(id: $id, input: {title: "Neu"}) { id } }', fn (self $t) => ['id' => $t->todo->id]],
            'moveTodo' => ['manager', 'mutation ($id: ID!, $m: ID!) { moveTodo(id: $id, milestoneId: $m, position: 0) { id } }', fn (self $t) => ['id' => $t->todo->id, 'm' => Milestone::factory()->for($t->project)->create()->id]],
            'deleteTodo' => ['manager', 'mutation ($id: ID!) { deleteTodo(id: $id) { id } }', fn (self $t) => ['id' => $t->todo->id]],
            'createTask' => ['manager', 'mutation ($t: ID!, $u: ID!) { createTask(input: {todoId: $t, title: "Neu", assigneeId: $u}) { id } }', fn (self $t) => ['t' => $t->todo->id, 'u' => $t->users['member']->id]],
            'updateTask' => ['manager', 'mutation ($id: ID!) { updateTask(id: $id, input: {title: "Neu"}) { id } }', fn (self $t) => ['id' => $t->task->id]],
            'assignTask' => ['manager', 'mutation ($id: ID!, $u: ID!) { assignTask(id: $id, assigneeId: $u) { id } }', fn (self $t) => ['id' => $t->task->id, 'u' => $t->users['member']->id]],
            'moveTask' => ['member', 'mutation ($id: ID!) { moveTask(id: $id, status: DONE, position: 0) { id } }', fn (self $t) => ['id' => $t->task->id]],
            'moveTaskToTodo' => ['manager', 'mutation ($id: ID!, $t: ID!) { moveTaskToTodo(id: $id, todoId: $t) { id } }', fn (self $t) => ['id' => $t->task->id, 't' => Todo::factory()->for($t->milestone)->create()->id]],
            'deleteTask' => ['manager', 'mutation ($id: ID!) { deleteTask(id: $id) { id } }', fn (self $t) => ['id' => $t->task->id]],
            'setTaskLabels' => ['manager', 'mutation ($id: ID!, $l: [ID!]!) { setTaskLabels(taskId: $id, labelIds: $l) { id } }', fn (self $t) => ['id' => $t->task->id, 'l' => [$t->labelOfTheProject()]]],
            'setProjectMember' => ['owner', 'mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "member") { id } }', fn (self $t) => ['p' => $t->project->id, 'u' => $t->users['stranger']->id]],
            'removeProjectMember' => ['owner', 'mutation ($p: ID!, $u: ID!) { removeProjectMember(projectId: $p, userId: $u) { id } }', fn (self $t) => ['p' => $t->project->id, 'u' => $t->users['member']->id]],
        ];
    }

    /**
     * Gives the project a label and returns its id.
     */
    private function labelOfTheProject(): string
    {
        return (string) Label::factory()->for($this->project)->create()->id;
    }

    /**
     * @param  Closure(self): array<string, mixed>  $variables
     */
    #[DataProvider('changes')]
    public function test_every_kind_of_change_counts_exactly_once(string $actor, string $mutation, Closure $variables): void
    {
        Notification::fake();
        $variables = $variables($this);
        $before = $this->revision();

        $this->as($actor)->gql($mutation, $variables)->assertJsonMissingPath('errors');

        $this->assertSame($before + 1, $this->revision());
    }

    public function test_a_comment_does_not_count(): void
    {
        $before = $this->revision();

        $this->as('member')->gql('mutation ($t: ID!, $body: RichText!) { addComment(input: {taskId: $t, body: $body}) { id } }', [
            't' => $this->task->id, 'body' => $this->doc('Gesehen'),
        ])->assertJsonMissingPath('errors');

        $this->assertSame($before, $this->revision());
    }

    public function test_reading_does_not_count_and_the_project_shows_its_revision(): void
    {
        $this->as('manager')->gql('mutation ($id: ID!) { updateProject(id: $id, input: {name: "Neu"}) { id } }', ['id' => $this->project->id]);
        $before = $this->revision();

        $this->as('member')->gql('query ($id: ID!) { project(id: $id) { planerRevision milestones { todos { tasks { id } } } } }', ['id' => $this->project->id])
            ->assertJsonPath('data.project.planerRevision', $before);

        $this->assertSame($before, $this->revision());
    }

    public function test_a_refused_change_does_not_count(): void
    {
        $before = $this->revision();

        // The task is saved, then its label is refused: the transaction rolls back.
        $this->as('manager')->gql('mutation ($t: ID!) { createTask(input: {todoId: $t, title: "Neu", labelIds: ["999999"]}) { id } }', ['t' => $this->todo->id])
            ->assertGraphQLValidationKeys(['input.labelIds']);

        $this->assertSame($before, $this->revision());
    }

    public function test_several_changes_in_one_transaction_count_once(): void
    {
        $before = $this->revision();

        DB::transaction(function (): void {
            $this->milestone->update(['title' => 'Eins']);
            $this->todo->update(['title' => 'Zwei']);
            $this->task->update(['title' => 'Drei']);
        });

        $this->assertSame($before + 1, $this->revision());
    }

    public function test_a_change_outside_a_transaction_counts_at_once(): void
    {
        $before = $this->revision();

        $this->task->update(['title' => 'Ohne Transaktion']);

        $this->assertSame($before + 1, $this->revision());
    }
}
