<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\GraphQL\Builders\TodoBuilder;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    /** @var array{project: Project, milestone: Milestone, todo: Todo, task: Task} */
    private array $mine;

    /** @var array{project: Project, milestone: Milestone, todo: Todo, task: Task} */
    private array $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create();
        $this->mine = $this->projectTree(Project::factory()->withMember($this->member, ProjectRole::MEMBER)->create());
        $this->other = $this->projectTree(Project::factory()->create());
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function models(): array
    {
        return [
            'projects' => [Project::class, 'project'],
            'milestones' => [Milestone::class, 'milestone'],
            'todos' => [Todo::class, 'todo'],
            'tasks' => [Task::class, 'task'],
        ];
    }

    /**
     * @param  class-string<Project|Milestone|Todo|Task>  $model
     */
    #[DataProvider('models')]
    public function test_member_sees_only_own_projects_content(string $model, string $key): void
    {
        $this->actingAs($this->member);

        $this->assertSame([$this->mine[$key]->id], $model::query()->visibleToViewer()->pluck('id')->all());
    }

    /**
     * @param  class-string<Project|Milestone|Todo|Task>  $model
     */
    #[DataProvider('models')]
    public function test_admin_sees_everything(string $model, string $key): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertEqualsCanonicalizing(
            [$this->mine[$key]->id, $this->other[$key]->id],
            $model::query()->visibleToViewer()->pluck('id')->all(),
        );
    }

    /**
     * @param  class-string<Project|Milestone|Todo|Task>  $model
     */
    #[DataProvider('models')]
    public function test_guest_sees_nothing(string $model, string $key): void
    {
        $this->assertSame(0, $model::query()->visibleToViewer()->count());
    }

    /**
     * @param  class-string<Project|Milestone|Todo|Task>  $model
     */
    #[DataProvider('models')]
    public function test_removed_member_sees_nothing(string $model, string $key): void
    {
        $this->mine['project']->members()->detach($this->member);
        $this->actingAs($this->member);

        $this->assertSame(0, $model::query()->visibleToViewer()->count());
    }

    public function test_visible_to_checks_the_given_user_not_the_viewer(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame([$this->mine['project']->id], Project::query()->visibleTo($this->member)->pluck('id')->all());
    }

    public function test_find_returns_null_for_invisible_records(): void
    {
        $this->actingAs($this->member);

        $this->assertNull(Task::query()->visibleToViewer()->find($this->other['task']->id));
        $this->assertNotNull(Task::query()->visibleToViewer()->find($this->mine['task']->id));
    }

    public function test_my_tasks_are_assigned_and_still_visible(): void
    {
        $assignedMine = Task::factory()->for($this->mine['todo'])->create(['assigned_user_id' => $this->member->id]);
        // Assigned in a project the member is not part of (anymore): hidden.
        Task::factory()->for($this->other['todo'])->create(['assigned_user_id' => $this->member->id]);

        $this->actingAs($this->member);

        $this->assertSame(
            [$assignedMine->id],
            Task::query()->assignedToViewer()->visibleToViewer()->pluck('id')->all(),
        );
    }

    public function test_guest_has_no_assigned_tasks(): void
    {
        Task::factory()->for($this->mine['todo'])->create(['assigned_user_id' => $this->member->id]);

        $this->assertSame(0, Task::query()->assignedToViewer()->count());
    }

    public function test_todo_builder_filters_by_project(): void
    {
        $secondMilestone = Milestone::factory()->for($this->mine['project'])->create();
        $secondTodo = Todo::factory()->for($secondMilestone)->create();

        $ids = (new TodoBuilder)->inProject(Todo::query(), $this->mine['project']->id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$this->mine['todo']->id, $secondTodo->id], $ids);
    }

    public function test_todo_builder_with_scope_hides_foreign_project(): void
    {
        $this->actingAs($this->member);

        $query = (new TodoBuilder)->inProject(Todo::query(), $this->other['project']->id)->visibleToViewer();

        $this->assertSame(0, $query->count());
    }

    /**
     * @return array{project: Project, milestone: Milestone, todo: Todo, task: Task}
     */
    private function projectTree(Project $project): array
    {
        $milestone = Milestone::factory()->for($project)->create();
        $todo = Todo::factory()->for($milestone)->create();
        $task = Task::factory()->for($todo)->create();

        return compact('project', 'milestone', 'todo', 'task');
    }
}
