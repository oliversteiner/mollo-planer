<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Services\Deletion;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The trash in the backend (docs/auftrag-m2.md §2, §6): one deletion id per
 * deletion, restoring exactly that deletion, order and status, the planer
 * revision, the ground rule §7.3 and that nothing deleted shows anywhere.
 */
class TrashTest extends GraphQLTestCase
{
    private Deletion $deletion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deletion = app(Deletion::class);
        $this->actingAs($this->users['owner']);
    }

    public function test_deleting_a_milestone_gives_its_children_the_same_deletion_id(): void
    {
        $comment = Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create();

        $this->deletion->delete($this->milestone);

        $id = $this->milestone->fresh()?->deletion_id ?? Milestone::onlyTrashed()->find($this->milestone->id)->deletion_id;
        $this->assertNotNull($id);
        foreach ([Todo::onlyTrashed()->find($this->todo->id), Task::onlyTrashed()->find($this->task->id), Comment::onlyTrashed()->find($comment->id)] as $child) {
            $this->assertSame($id, $child->deletion_id);
            $this->assertSame($this->users['owner']->id, $child->deleted_by);
        }
    }

    public function test_a_task_deleted_before_keeps_its_own_deletion_and_stays_in_the_trash(): void
    {
        $earlier = Task::factory()->for($this->todo)->create(['title' => 'Vorher gelöscht']);
        $this->deletion->delete($earlier);
        $earlierId = Task::onlyTrashed()->find($earlier->id)->deletion_id;

        $this->deletion->delete($this->milestone);
        $milestoneId = Milestone::onlyTrashed()->find($this->milestone->id)->deletion_id;
        $this->assertNotSame($earlierId, $milestoneId);
        $this->assertSame($earlierId, Task::onlyTrashed()->find($earlier->id)->deletion_id);

        $this->deletion->restore($milestoneId, $this->users['owner']);

        $this->assertNotNull(Milestone::find($this->milestone->id));
        $this->assertNotNull(Todo::find($this->todo->id));
        $this->assertNotNull(Task::find($this->task->id));
        $this->assertNull(Task::find($earlier->id));
        $this->assertSame($earlier->id, $this->deletion->topOf($earlierId)->id);
    }

    public function test_restoring_into_a_deleted_parent_is_refused_and_names_what_comes_first(): void
    {
        $this->deletion->delete($this->task);
        $taskDeletion = Task::onlyTrashed()->find($this->task->id)->deletion_id;
        $this->deletion->delete($this->milestone);

        try {
            $this->deletion->restore($taskDeletion, $this->users['owner']);
            $this->fail('The restore should be refused.');
        } catch (ValidationException $refusal) {
            $this->assertSame(
                'Zuerst muss der Milestone „'.$this->milestone->title.'“ aus dem Papierkorb zurück.',
                $refusal->errors()['deletionId'][0],
            );
        }

        $this->assertNull(Task::find($this->task->id));
    }

    public function test_the_gaps_close_and_restored_elements_go_to_the_end(): void
    {
        $second = Milestone::factory()->for($this->project)->create();
        $third = Milestone::factory()->for($this->project)->create();
        $this->deletion->delete($this->milestone);

        $this->assertSame([0, 1], [$second->fresh()->position, $third->fresh()->position]);

        $this->deletion->restore(Milestone::onlyTrashed()->find($this->milestone->id)->deletion_id, $this->users['owner']);
        $this->assertSame([0, 1, 2], [$second->fresh()->position, $third->fresh()->position, $this->milestone->fresh()->position]);

        $todo2 = Todo::factory()->for($this->milestone)->create();
        $this->deletion->delete($this->todo);
        $this->assertSame(0, $todo2->fresh()->position);
        $this->deletion->restore(Todo::onlyTrashed()->find($this->todo->id)->deletion_id, $this->users['owner']);
        $this->assertSame([0, 1], [$todo2->fresh()->position, $this->todo->fresh()->position]);
    }

    public function test_kanban_columns_have_no_gaps_and_restored_tasks_come_last(): void
    {
        $tasks = collect([1, 2])->map(fn () => Task::factory()->for($this->todo)->create());
        $positions = fn () => Task::query()->where('project_id', $this->project->id)->where('status', TaskStatus::OPEN->value)->orderBy('board_position')->pluck('board_position', 'id')->all();
        $this->task->forceFill(['board_position' => 0])->save();
        $tasks[0]->forceFill(['board_position' => 1])->save();
        $tasks[1]->forceFill(['board_position' => 2])->save();

        $this->deletion->delete($this->task);
        $this->assertSame([$tasks[0]->id => 0, $tasks[1]->id => 1], $positions());

        $this->deletion->restore(Task::onlyTrashed()->find($this->task->id)->deletion_id, $this->users['owner']);
        $this->assertSame([$tasks[0]->id => 0, $tasks[1]->id => 1, $this->task->id => 2], $positions());
    }

    public function test_a_todo_whose_last_open_task_is_deleted_is_done_and_opens_again_on_restore(): void
    {
        Task::factory()->for($this->todo)->create(['status' => TaskStatus::DONE]);
        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);

        $this->deletion->delete($this->task);
        $this->assertSame(ItemStatus::DONE, $this->todo->fresh()->status);
        $this->assertSame(ItemStatus::DONE, $this->milestone->fresh()->status);

        $this->deletion->restore(Task::onlyTrashed()->find($this->task->id)->deletion_id, $this->users['owner']);
        $this->assertSame(ItemStatus::OPEN, $this->todo->fresh()->status);
        $this->assertSame(ItemStatus::OPEN, $this->milestone->fresh()->status);
    }

    public function test_the_planer_revision_counts_delete_and_restore_once_but_not_purge(): void
    {
        Todo::factory()->for($this->milestone)->has(Task::factory()->count(3))->create();
        $revision = fn (): int => (int) DB::table('projects')->where('id', $this->project->id)->value('planer_revision');
        $start = $revision();

        $this->deletion->delete($this->milestone);
        $this->assertSame($start + 1, $revision());

        $id = Milestone::onlyTrashed()->find($this->milestone->id)->deletion_id;
        $this->deletion->restore($id, $this->users['owner']);
        $this->assertSame($start + 2, $revision());

        $this->deletion->delete($this->task);
        $this->deletion->purge(Task::onlyTrashed()->find($this->task->id)->deletion_id);
        $this->assertSame($start + 3, $revision());
        $this->assertNull(Task::withTrashed()->find($this->task->id));
    }

    public function test_restoring_sends_no_mails(): void
    {
        Notification::fake();
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id])->save();
        $this->deletion->delete($this->task);

        $this->deletion->restore(Task::onlyTrashed()->find($this->task->id)->deletion_id, $this->users['owner']);

        Notification::assertNothingSent();
    }

    public function test_labels_stay_on_a_deleted_task_and_memberships_on_a_deleted_project(): void
    {
        $this->task->labels()->attach(Label::factory()->for($this->project)->create());

        $this->deletion->delete($this->project);
        $id = Project::onlyTrashed()->find($this->project->id)->deletion_id;

        $this->assertSame(1, DB::table('label_task')->where('task_id', $this->task->id)->count());
        $this->assertSame(3, DB::table('project_user')->where('project_id', $this->project->id)->count());

        $this->deletion->restore($id, $this->users['owner']);
        $this->assertNotNull(Task::find($this->task->id));
        $this->assertSame('owner', $this->project->fresh()->roleOf($this->users['owner'])?->key);
    }

    public function test_purge_and_empty_trash_delete_for_good(): void
    {
        $other = Task::factory()->for($this->todo)->create();
        $comment = Comment::factory()->for($other)->for($this->users['owner'], 'author')->create();
        $this->deletion->delete($this->task);
        $this->deletion->delete($comment);
        $this->deletion->delete($this->todo);

        $this->assertSame(3, $this->deletion->emptyProjectTrash($this->project));

        $this->assertNull(Todo::withTrashed()->find($this->todo->id));
        $this->assertNull(Task::withTrashed()->find($this->task->id));
        $this->assertNull(Comment::withTrashed()->find($comment->id));
        $this->assertSame([], $this->deletion->deletionIdsIn($this->project));
    }

    public function test_a_project_in_the_trash_does_not_block_changing_its_only_owner(): void
    {
        $chiara = $this->users['owner'];
        $this->deletion->delete($this->project);

        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: {role: "lesend"}) { role { key } } }', ['id' => $chiara->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateUser.role.key', 'lesend');
    }

    public function test_restoring_a_project_without_holder_makes_the_restorer_a_member_with_the_creator_role(): void
    {
        $alone = Project::factory()->withMember($this->users['manager'], ProjectRole::OWNER)->create(['name' => 'Sommerfest']);
        $this->deletion->delete($alone);
        $this->users['manager']->forceFill(['role_id' => Role::byKey('system', 'lesend')->id])->save();
        Rights::clearCache();

        $this->deletion->restore(Project::onlyTrashed()->find($alone->id)->deletion_id, $this->users['admin']);

        $alone = $alone->fresh();
        $this->assertSame('owner', $alone->roleOf($this->users['admin'])?->key);
        $this->assertSame('owner', $alone->roleOf($this->users['manager'])?->key);
    }

    public function test_restoring_a_project_with_a_holder_changes_no_membership(): void
    {
        $this->deletion->delete($this->project);
        $this->deletion->restore(Project::onlyTrashed()->find($this->project->id)->deletion_id, $this->users['admin']);

        $this->assertNull($this->project->fresh()->roleOf($this->users['admin']));
    }

    public function test_a_deleted_task_shows_in_no_query(): void
    {
        $this->task->forceFill(['assigned_user_id' => $this->users['member']->id, 'due_date' => now()->addDay()])->save();
        Comment::factory()->for($this->task)->for($this->users['owner'], 'author')->create();
        $this->deletion->delete($this->task);

        $this->as('member')->gql('query ($p: ID!) {
            myTasks { id }
            dashboard { myOpenTasks { id } upcomingTodos(limit: 50) { id progress { total } } }
            kanban(projectId: $p) { tasks { id } }
            project(id: $p) { progress { total } milestones { progress { total } todos { progress { total } tasks { id } } } }
        }', ['p' => $this->project->id])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.myTasks', [])
            ->assertJsonPath('data.dashboard.myOpenTasks', [])
            ->assertJsonPath('data.project.progress.total', 0)
            ->assertJsonPath('data.project.milestones.0.progress.total', 0)
            ->assertJsonPath('data.project.milestones.0.todos.0.progress.total', 0)
            ->assertJsonPath('data.project.milestones.0.todos.0.tasks', [])
            ->assertJsonPath('data.kanban.*.tasks', array_fill(0, count(TaskStatus::cases()), []));

        $this->as('member')->gql('query ($id: ID!) { task(id: $id) { id } }', ['id' => $this->task->id])
            ->assertJsonPath('data.task', null);
    }

    public function test_a_deleted_project_shows_nowhere(): void
    {
        $this->deletion->delete($this->project);

        $this->as('owner')->gql('query ($p: ID!) { projects { id } project(id: $p) { id } }', ['p' => $this->project->id])
            ->assertJsonPath('data.projects', [])
            ->assertJsonPath('data.project', null);
    }
}
