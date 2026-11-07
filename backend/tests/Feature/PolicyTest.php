<?php

namespace Tests\Feature;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\ProjectPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Milestone $milestone;

    private Todo $todo;

    private Task $task;

    /** @var array<string, User> */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = [
            'owner' => User::factory()->create(),
            'manager' => User::factory()->create(),
            'member' => User::factory()->create(),
            'stranger' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
        ];

        $this->project = Project::factory()
            ->withMember($this->users['owner'], ProjectRole::Owner)
            ->withMember($this->users['manager'], ProjectRole::Manager)
            ->withMember($this->users['member'], ProjectRole::Member)
            ->create();
        $this->milestone = Milestone::factory()->for($this->project)->create();
        $this->todo = Todo::factory()->for($this->milestone)->create();
        $this->task = Task::factory()->for($this->todo)->create();
    }

    /**
     * Policy method, model, ability it must ask the permission table for.
     *
     * @return array<string, array{string, string, ProjectAbility}>
     */
    public static function policyAbilities(): array
    {
        return [
            'project update' => ['update', 'project', ProjectAbility::ProjectUpdate],
            'project delete' => ['delete', 'project', ProjectAbility::ProjectDelete],
            'project manageMembers' => ['manageMembers', 'project', ProjectAbility::MembersManage],
            'project createMilestone' => ['createMilestone', 'project', ProjectAbility::MilestoneCreate],
            'milestone update' => ['update', 'milestone', ProjectAbility::MilestoneUpdate],
            'milestone delete' => ['delete', 'milestone', ProjectAbility::MilestoneDelete],
            'milestone createTodo' => ['createTodo', 'milestone', ProjectAbility::TodoCreate],
            'todo update' => ['update', 'todo', ProjectAbility::TodoUpdate],
            'todo delete' => ['delete', 'todo', ProjectAbility::TodoDelete],
            'todo createTask' => ['createTask', 'todo', ProjectAbility::TaskCreate],
            'task update' => ['update', 'task', ProjectAbility::TaskUpdate],
            'task assign' => ['assign', 'task', ProjectAbility::TaskAssign],
            'task move' => ['move', 'task', ProjectAbility::TaskMove],
            'task delete' => ['delete', 'task', ProjectAbility::TaskDelete],
            'task createComment' => ['createComment', 'task', ProjectAbility::CommentCreate],
        ];
    }

    #[DataProvider('policyAbilities')]
    public function test_policy_follows_the_permission_table(string $method, string $model, ProjectAbility $ability): void
    {
        $target = $this->{$model};

        foreach (ProjectRole::cases() as $role) {
            $this->assertSame(
                ProjectPermissions::roleAllows($role, $ability),
                $this->users[$role->value]->can($method, $target),
                "{$role->value} {$method} {$model}",
            );
        }

        $this->assertTrue($this->users['admin']->can($method, $target), "admin {$method} {$model}");
        $this->assertFalse($this->users['stranger']->can($method, $target), "stranger {$method} {$model}");
    }

    public function test_only_admins_create_projects(): void
    {
        $this->assertTrue($this->users['admin']->can('create', Project::class));
        $this->assertFalse($this->users['owner']->can('create', Project::class));
    }

    public function test_guests_are_denied(): void
    {
        $this->assertGuest();
        $this->assertTrue(Gate::denies('move', $this->task));
        $this->assertTrue(Gate::denies('viewAny', User::class));
    }

    public function test_author_edits_and_deletes_own_comment(): void
    {
        $comment = $this->commentBy($this->users['member']);

        $this->assertTrue($this->users['member']->can('update', $comment));
        $this->assertTrue($this->users['member']->can('delete', $comment));
    }

    public function test_nobody_edits_someone_elses_comment_not_even_admin(): void
    {
        $comment = $this->commentBy($this->users['member']);

        foreach (['owner', 'manager', 'admin', 'stranger'] as $name) {
            $this->assertFalse($this->users[$name]->can('update', $comment), $name);
        }
    }

    public function test_moderators_delete_someone_elses_comment(): void
    {
        $comment = $this->commentBy($this->users['owner']);

        $this->assertTrue($this->users['manager']->can('delete', $comment));
        $this->assertTrue($this->users['admin']->can('delete', $comment));
        $this->assertFalse($this->users['member']->can('delete', $comment));
        $this->assertFalse($this->users['stranger']->can('delete', $comment));
    }

    public function test_author_removed_from_project_loses_access_to_own_comment(): void
    {
        $comment = $this->commentBy($this->users['member']);
        $this->project->members()->detach($this->users['member']);

        $this->assertFalse($this->users['member']->can('update', $comment));
        $this->assertFalse($this->users['member']->can('delete', $comment));
    }

    public function test_comment_of_deleted_author_can_only_be_moderated(): void
    {
        $comment = $this->commentBy($this->users['member']);
        $this->users['member']->delete();
        $comment->refresh();

        $this->assertNull($comment->user_id);
        $this->assertTrue($this->users['owner']->can('delete', $comment));
        $this->assertFalse($this->users['owner']->can('update', $comment));
    }

    public function test_user_list_is_for_admins_and_member_managers(): void
    {
        $this->assertTrue($this->users['admin']->can('viewAny', User::class));
        $this->assertTrue($this->users['owner']->can('viewAny', User::class));
        $this->assertTrue($this->users['manager']->can('viewAny', User::class));
        $this->assertFalse($this->users['member']->can('viewAny', User::class));
        $this->assertFalse($this->users['stranger']->can('viewAny', User::class));
    }

    public function test_only_admins_manage_users(): void
    {
        $admin = $this->users['admin'];
        $owner = $this->users['owner'];

        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $owner));
        $this->assertTrue($admin->can('delete', $owner));

        $this->assertFalse($owner->can('create', User::class));
        $this->assertFalse($owner->can('update', $owner));
        $this->assertFalse($owner->can('update', $this->users['member']));
        $this->assertFalse($owner->can('delete', $this->users['member']));
    }

    public function test_admin_cannot_delete_or_demote_themselves(): void
    {
        $admin = $this->users['admin'];

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($admin->can('update', [$admin, ['is_admin' => false]]));
        $this->assertTrue($admin->can('update', [$admin, ['name' => 'New Name']]));
        $this->assertTrue($admin->can('update', [$this->users['owner'], ['is_admin' => true]]));
    }

    private function commentBy(User $author): Comment
    {
        return Comment::factory()->for($this->task)->for($author, 'author')->create();
    }
}
