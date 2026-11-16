<?php

namespace Tests\Feature;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Releases and commits (spec/format-v2.md, §5.3). The rules run on save, so
 * they hold for every way in.
 */
class ReleaseRulesTest extends TestCase
{
    use RefreshDatabase;

    private Milestone $milestone;

    private Todo $todo;

    protected function setUp(): void
    {
        parent::setUp();

        $project = Project::factory()->create();
        $this->milestone = Milestone::factory()->for($project)->create();
        $this->todo = Todo::factory()->for($this->milestone)->create();
    }

    private function refusedWith(Closure $change, string $key): void
    {
        try {
            $change();
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($key, $error->errors());

            // A refused save keeps the changed values in memory; reload before the next attempt.
            $this->milestone->refresh();
            $this->todo->refresh();

            return;
        }

        $this->fail("Expected a validation error on {$key}.");
    }

    public function test_version_and_status_belong_together(): void
    {
        $this->refusedWith(fn () => $this->milestone->update(['release_version' => '1.4']), 'input.releaseStatus');
        $this->refusedWith(fn () => $this->milestone->update(['release_status' => ReleaseStatus::PLANNED]), 'input.releaseStatus');

        $this->milestone->update(['release_version' => '1.4', 'release_status' => ReleaseStatus::PLANNED]);
        $this->assertSame(ReleaseStatus::PLANNED, $this->milestone->fresh()->release_status);
    }

    public function test_version_has_the_form_major_dot_minor(): void
    {
        $this->refusedWith(fn () => $this->milestone->update(['release_version' => '1', 'release_status' => ReleaseStatus::PLANNED]), 'input.releaseVersion');
        $this->refusedWith(fn () => $this->milestone->update(['release_version' => '1.4.2', 'release_status' => ReleaseStatus::PLANNED]), 'input.releaseVersion');
    }

    public function test_a_released_version_has_only_done_tasks(): void
    {
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::OPEN]);

        $this->refusedWith(fn () => $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]), 'input.releaseStatus');
    }

    /**
     * @return list<string>
     */
    private function releaseMessages(): array
    {
        try {
            $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]);
        } catch (ValidationException $error) {
            $this->milestone->refresh();

            return $error->errors()['input.releaseStatus'];
        }

        $this->fail('Expected the release to be refused.');
    }

    public function test_the_messages_name_the_tasks_by_reason_with_the_status_of_ideas_and_planned_ones(): void
    {
        Task::factory()->for($this->todo)->create(['title' => 'Mockup Desktop', 'status' => TaskStatus::OPEN]);
        Task::factory()->for($this->todo)->create(['title' => 'Task Idee', 'status' => TaskStatus::IDEA]);
        Task::factory()->for($this->todo)->create(['title' => 'Farben festlegen', 'status' => TaskStatus::DONE, 'commit' => null]);
        Task::factory()->for($this->todo)->create(['title' => 'Logo', 'status' => TaskStatus::DONE, 'commit' => 'none']);

        $this->assertSame([
            'Noch nicht erledigt: Mockup Desktop, Task Idee (Idee). Erledige sie oder verschiebe sie in einen anderen Milestone.',
            'Ohne Commit: Farben festlegen. Trage einen Commit oder „none“ ein.',
        ], $this->releaseMessages());
    }

    public function test_the_messages_name_at_most_five_tasks(): void
    {
        foreach (range(1, 8) as $number) {
            Task::factory()->for($this->todo)->create(['title' => "Task {$number}", 'status' => TaskStatus::OPEN]);
        }

        $this->assertSame(
            ['Noch nicht erledigt: Task 1, Task 2, Task 3, Task 4, Task 5 und 3 weitere. Erledige sie oder verschiebe sie in einen anderen Milestone.'],
            $this->releaseMessages(),
        );
    }

    public function test_done_tasks_of_a_released_version_need_a_commit_or_none(): void
    {
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE, 'commit' => null]);

        $this->refusedWith(fn () => $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]), 'input.releaseStatus');

        Task::query()->update(['commit' => 'none']);
        $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]);

        $this->assertSame(ReleaseStatus::RELEASED, $this->milestone->fresh()->release_status);
    }

    public function test_a_task_of_a_released_version_must_be_done(): void
    {
        $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]);

        $this->refusedWith(fn () => Task::factory()->for($this->todo)->create(['status' => TaskStatus::OPEN]), 'status');
        $this->refusedWith(fn () => Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE, 'commit' => null]), 'input.commit');
    }

    public function test_a_task_cannot_be_moved_into_a_released_version_while_open(): void
    {
        $this->milestone->update(['release_version' => '1.0', 'release_status' => ReleaseStatus::RELEASED]);
        $elsewhere = Todo::factory()->for(Milestone::factory()->for($this->milestone->project)->create())->create();
        $task = Task::factory()->for($elsewhere)->create(['status' => TaskStatus::OPEN]);

        $this->refusedWith(fn () => $task->update(['todo_id' => $this->todo->id]), 'status');
    }

    public function test_commit_is_a_lower_case_hash_or_none_and_none_is_only_for_done(): void
    {
        $this->refusedWith(fn () => Task::factory()->for($this->todo)->create(['commit' => 'ABC1234']), 'input.commit');
        $this->refusedWith(fn () => Task::factory()->for($this->todo)->create(['commit' => 'abc']), 'input.commit');
        $this->refusedWith(fn () => Task::factory()->for($this->todo)->create(['status' => TaskStatus::OPEN, 'commit' => 'none']), 'input.commit');

        $task = Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE, 'commit' => 'a1b2c3d']);
        $this->assertSame('a1b2c3d', $task->fresh()->commit);
    }

    public function test_a_released_version_without_tasks_is_fine(): void
    {
        $this->milestone->update(['release_version' => '2.0', 'release_status' => ReleaseStatus::RELEASED]);

        $this->assertSame(ReleaseStatus::RELEASED, $this->milestone->fresh()->release_status);
    }
}
