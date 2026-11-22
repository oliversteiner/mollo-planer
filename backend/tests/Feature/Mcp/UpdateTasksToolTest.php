<?php

namespace Tests\Feature\Mcp;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Mcp\Servers\MolloPlanerServer;
use App\Mcp\Support\GraphQLRunner;
use App\Mcp\Tools\UpdateTasksTool;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Tests\Feature\GraphQL\GraphQLTestCase;

/**
 * update_tasks (task 30 B): the rules and rights are those of the app, because the tool goes through export_plan and
 * import_plan (partial). All or nothing.
 */
class UpdateTasksToolTest extends GraphQLTestCase
{
    private Task $second;

    private Task $inRelease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->task->forceFill(['status' => TaskStatus::OPEN])->save();
        $this->second = Task::factory()->for($this->todo)->create(['board_position' => 1, 'status' => TaskStatus::OPEN]);

        $released = Milestone::factory()->for($this->project)->create(['position' => 1]);
        $todo = Todo::factory()->for($released)->create();
        $this->inRelease = Task::factory()->for($todo)->create(['board_position' => 2, 'status' => TaskStatus::DONE, 'commit' => 'abc1234']);
        $released->forceFill(['release_version' => '0.9', 'release_status' => ReleaseStatus::RELEASED])->save();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function update(string $as, array $arguments): mixed
    {
        return MolloPlanerServer::actingAs($this->users[$as])
            ->tool(UpdateTasksTool::class, [
                'project_id' => (string) $this->project->id,
                'base_revision' => (int) $this->project->fresh()->planer_revision,
                ...$arguments,
            ]);
    }

    public function test_it_sets_status_and_commit_and_reports_before_and_after(): void
    {
        $revision = (int) $this->project->fresh()->planer_revision;

        $this->update('owner', ['tasks' => [
            ['id' => "k-{$this->task->id}", 'status' => 'done', 'commit' => 'abc1234'],
            ['id' => (string) $this->second->id, 'status' => 'in_progress'],
        ]])
            ->assertOk()
            ->assertSee('"before"')
            ->assertSee('"in_progress"')
            ->assertSee('abc1234');

        $this->assertSame(TaskStatus::DONE, $this->task->fresh()->status);
        $this->assertSame('abc1234', $this->task->fresh()->commit);
        $this->assertSame(TaskStatus::IN_PROGRESS, $this->second->fresh()->status);
        $this->assertGreaterThan($revision, (int) $this->project->fresh()->planer_revision);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $revision = (int) $this->project->fresh()->planer_revision;

        $this->update('owner', ['dry_run' => true, 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])
            ->assertOk()
            ->assertSee('"dry_run":true')
            ->assertSee('"review"');

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
        $this->assertSame($revision, (int) $this->project->fresh()->planer_revision);
    }

    public function test_null_clears_commit_and_assignee(): void
    {
        $this->task->forceFill([
            'commit' => 'abc1234',
            'assigned_user_id' => $this->users['member']->id,
        ])->saveQuietly();

        $this->update('owner', ['tasks' => [[
            'id' => "k-{$this->task->id}",
            'commit' => null,
            'assigneeEmail' => null,
        ]]])->assertOk();

        $this->assertNull($this->task->fresh()->commit);
        $this->assertNull($this->task->fresh()->assigned_user_id);
    }

    public function test_a_change_after_export_is_rejected_before_import(): void
    {
        $runner = app(GraphQLRunner::class);
        $mock = \Mockery::mock($runner)->makePartial();
        $mock->shouldReceive('run')->andReturnUsing(function (string $query, array $variables) use ($runner): array {
            if (str_contains($query, 'importPlan(')) {
                $this->task->update(['title' => 'Concurrent change']);
            }

            return $runner->run($query, $variables);
        });
        $this->app->instance(GraphQLRunner::class, $mock);

        $this->update('owner', ['tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])
            ->assertHasErrors()
            ->assertSee('Der Plan wurde inzwischen geändert');

        $this->assertSame('Concurrent change', $this->task->fresh()->title);
        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
    }

    public function test_the_right_to_import_is_needed(): void
    {
        $this->update('member', ['tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])->assertHasErrors();
        $this->update('stranger', ['tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])->assertHasErrors();

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
    }

    public function test_the_release_rule_and_the_commit_format_apply(): void
    {
        $this->update('owner', ['tasks' => [['id' => "k-{$this->inRelease->id}", 'status' => 'open']]])->assertHasErrors();
        $this->update('owner', ['tasks' => [['id' => "k-{$this->inRelease->id}", 'commit' => null]]])->assertHasErrors();
        $this->update('owner', ['tasks' => [['id' => "k-{$this->task->id}", 'commit' => 'not a hash']]])->assertHasErrors();

        $this->assertSame(TaskStatus::DONE, $this->inRelease->fresh()->status);
        $this->assertSame('abc1234', $this->inRelease->fresh()->commit);
        $this->assertNull($this->task->fresh()->commit);
    }

    public function test_an_assignee_must_be_a_member(): void
    {
        $this->update('owner', ['tasks' => [['id' => "k-{$this->task->id}", 'assigneeEmail' => $this->users['stranger']->email]]])->assertHasErrors();
        $this->assertNull($this->task->fresh()->assigned_user_id);

        $this->update('owner', ['tasks' => [['id' => "k-{$this->task->id}", 'assigneeEmail' => $this->users['member']->email]]])->assertOk();
        $this->assertSame($this->users['member']->id, $this->task->fresh()->assigned_user_id);
    }

    public function test_an_old_base_revision_stops_everything(): void
    {
        $revision = (int) $this->project->fresh()->planer_revision;

        $this->update('owner', ['base_revision' => $revision - 1, 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])
            ->assertHasErrors()
            ->assertSee('Der Plan wurde inzwischen geändert');
        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);

        $this->update('owner', ['base_revision' => $revision, 'tasks' => [['id' => "k-{$this->task->id}", 'status' => 'review']]])->assertOk();
        $this->assertSame(TaskStatus::REVIEW, $this->task->fresh()->status);
    }

    public function test_all_or_nothing(): void
    {
        $this->update('owner', ['tasks' => [
            ['id' => "k-{$this->task->id}", 'status' => 'review'],
            ['id' => "k-{$this->second->id}", 'commit' => 'not a hash'],
        ]])->assertHasErrors();

        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);

        $this->update('owner', ['tasks' => [
            ['id' => "k-{$this->task->id}", 'status' => 'review'],
            ['id' => 'k-999999', 'status' => 'open'],
        ]])->assertHasErrors()->assertSee('tasks[1].id');
        $this->assertSame(TaskStatus::OPEN, $this->task->fresh()->status);
    }
}
