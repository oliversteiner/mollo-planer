<?php

namespace Tests\Feature;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\ProjectMembership;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rules of docs/rollen-funktionen-rechte.md that the standard rights
 * alone do not express: own tasks, self assignment, guests, owner rights.
 */
class RightsRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $member;

    private User $otherMember;

    private User $guest;

    private Project $project;

    private Task $ownTask;

    private Task $foreignTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->member = User::factory()->create();
        $this->otherMember = User::factory()->create();
        $this->guest = User::factory()->create();

        $this->project = Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->manager, ProjectRole::MANAGER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->withMember($this->otherMember, ProjectRole::MEMBER)
            ->withMember($this->guest, ProjectRole::GUEST)
            ->create();

        $todo = Todo::factory()->for(Milestone::factory()->for($this->project)->create())->create();
        $this->ownTask = Task::factory()->for($todo)->create();
        $this->ownTask->forceFill(['created_by' => $this->member->id])->saveQuietly();
        $this->foreignTask = Task::factory()->for($todo)->create();
        $this->foreignTask->forceFill(['created_by' => $this->owner->id])->saveQuietly();
    }

    public function test_member_changes_own_tasks_only(): void
    {
        $this->assertTrue($this->member->can('update', $this->ownTask));
        $this->assertFalse($this->member->can('update', $this->foreignTask));
        $this->assertTrue($this->manager->can('update', $this->foreignTask));
    }

    public function test_member_assigns_only_to_self(): void
    {
        $this->assertTrue($this->member->can('assign', $this->foreignTask));
        $this->assertTrue(Rights::mayAssignTask($this->member, $this->project, $this->member->id, null));
        $this->assertFalse(Rights::mayAssignTask($this->member, $this->project, $this->owner->id, null));
    }

    public function test_member_may_remove_only_their_own_assignment(): void
    {
        $this->assertTrue(Rights::mayAssignTask($this->member, $this->project, null, $this->member->id));
        $this->assertFalse(Rights::mayAssignTask($this->member, $this->project, null, $this->otherMember->id));
    }

    public function test_manager_assigns_anyone(): void
    {
        $this->assertTrue(Rights::mayAssignTask($this->manager, $this->project, $this->otherMember->id, null));
    }

    public function test_guest_reads_and_changes_nothing(): void
    {
        $this->assertTrue(Rights::canView($this->guest, $this->project));
        $this->assertSame([], Rights::projectRightsOf($this->guest, $this->project));
        $this->assertFalse(Rights::projectAllows($this->guest, $this->project, ProjectAbility::TASK_CREATE));
        $this->assertFalse($this->guest->can('update', $this->foreignTask));
        $this->assertFalse($this->guest->can('move', $this->foreignTask));
        $this->assertFalse($this->guest->can('createComment', $this->foreignTask));
        $this->assertFalse(Rights::mayAssignTask($this->guest, $this->project, $this->guest->id, null));
    }

    public function test_only_owners_change_owner_memberships(): void
    {
        $this->assertTrue(Rights::canChangeMembership($this->owner, $this->project, ProjectRole::MEMBER, ProjectRole::OWNER));
        $this->assertFalse(Rights::canChangeMembership($this->manager, $this->project, ProjectRole::MEMBER, ProjectRole::OWNER));
        $this->assertFalse(Rights::canChangeMembership($this->manager, $this->project, ProjectRole::OWNER, ProjectRole::MEMBER));
        $this->assertTrue(Rights::canChangeMembership($this->manager, $this->project, ProjectRole::MEMBER, ProjectRole::GUEST));
    }

    public function test_a_manager_cannot_make_himself_owner_through_membership_service(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ProjectMembership::class)->set($this->manager, $this->project, $this->member, ProjectRole::OWNER);
    }

    public function test_owner_manage_is_a_right_of_the_owner_role(): void
    {
        $this->assertTrue(Rights::projectRoleAllows(ProjectRole::OWNER, ProjectAbility::OWNER_MANAGE));
        $this->assertFalse(Rights::projectRoleAllows(ProjectRole::MANAGER, ProjectAbility::OWNER_MANAGE));
    }
}
