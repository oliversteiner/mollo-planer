<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\GraphQL\Mutations\CreateProject;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMembership;
use App\Support\AppSettings;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The ground rules of docs/rollen-funktionen-rechte.md, §7, follow rights and
 * not role names. Each test changes which role has which function, so a rule
 * that still looked at a name (owner, admin) would fail here.
 */
class RightsGroundRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $member;

    private Project $project;

    private ProjectMembership $membership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->member = User::factory()->create();

        $this->project = Project::factory()
            ->withMember($this->owner, ProjectRole::OWNER)
            ->withMember($this->manager, ProjectRole::MANAGER)
            ->withMember($this->member, ProjectRole::MEMBER)
            ->create();

        $this->membership = app(ProjectMembership::class);
    }

    // §7.3: every project keeps somebody with project.delete

    public function test_the_last_member_with_project_delete_cannot_be_demoted_or_removed(): void
    {
        try {
            $this->membership->set($this->owner, $this->project, $this->owner, ProjectRole::MANAGER);
            $this->fail('Demoting the last holder must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $this->membership->remove($this->owner, $this->project, $this->owner);
    }

    public function test_another_role_with_project_delete_keeps_the_project_alive(): void
    {
        $this->grant('project', 'manager', 'own-project');

        $this->membership->set($this->owner, $this->project, $this->owner, ProjectRole::MEMBER);

        $this->assertSame(ProjectRole::MEMBER, $this->project->fresh()->roleOf($this->owner));
        $this->assertSame(1, Rights::countHolders($this->project));
    }

    public function test_the_owner_role_without_project_delete_holds_nothing(): void
    {
        $this->revoke('project', 'owner', 'own-project');
        $this->grant('project', 'manager', 'own-project');

        $this->assertFalse(Rights::holdsProject($this->owner, ProjectRole::OWNER));
        $this->assertTrue(Rights::holdsProject($this->manager, ProjectRole::MANAGER));

        $this->expectException(ValidationException::class);
        $this->membership->remove($this->manager, $this->project, $this->manager);
    }

    public function test_a_holder_whose_system_role_caps_all_project_rights_does_not_count(): void
    {
        $readOnly = User::factory()->role(UserRole::LESEND)->create();
        $this->project->members()->attach($readOnly, ['role' => ProjectRole::OWNER->value]);

        $this->assertSame(1, Rights::countHolders($this->project));

        $this->expectException(ValidationException::class);
        $this->membership->remove($this->owner, $this->project, $this->owner);
    }

    public function test_someone_who_manages_any_project_may_remove_the_last_holder(): void
    {
        $admin = User::factory()->admin()->create();

        $this->membership->remove($admin, $this->project, $this->owner);

        $this->assertSame(0, Rights::countHolders($this->project));
    }

    // §7.4: roles with project.delete or owner.manage need owner.manage

    public function test_a_role_that_gains_project_delete_becomes_protected(): void
    {
        $this->assertTrue(Rights::canChangeMembership($this->manager, $this->project, null, ProjectRole::MEMBER));

        $this->grant('project', 'member', 'own-project');

        $this->assertTrue(Rights::isOwnershipRole(ProjectRole::MEMBER));
        $this->assertFalse(Rights::canChangeMembership($this->manager, $this->project, null, ProjectRole::MEMBER));
        $this->assertFalse(Rights::canChangeMembership($this->manager, $this->project, ProjectRole::MEMBER, ProjectRole::GUEST));
        $this->assertTrue(Rights::canChangeMembership($this->owner, $this->project, null, ProjectRole::MEMBER));
    }

    public function test_the_owner_role_without_ownership_rights_is_handled_with_members_manage(): void
    {
        $this->revoke('project', 'owner', 'own-project');

        $this->assertFalse(Rights::isOwnershipRole(ProjectRole::OWNER));
        $this->assertTrue(Rights::canChangeMembership($this->manager, $this->project, ProjectRole::MEMBER, ProjectRole::OWNER));
    }

    public function test_a_manager_with_owner_manage_grants_the_owner_role(): void
    {
        $this->expectNotToPerformAssertions();

        $this->grant('project', 'manager', 'own-project');

        $this->membership->set($this->manager, $this->project, $this->member, ProjectRole::OWNER);
    }

    public function test_without_owner_manage_the_owner_role_is_refused(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->membership->set($this->manager, $this->project, $this->member, ProjectRole::OWNER);
    }

    // §7.1 and §7.2: users.manage and roles.manage never get lost

    public function test_nobody_takes_users_manage_or_roles_manage_away_from_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->assertFalse($admin->can('update', [$admin, ['role' => UserRole::MITGLIED->value]]));
        $this->assertTrue($admin->can('update', [$admin, ['name' => 'New name']]));
    }

    public function test_changing_to_a_role_that_keeps_both_rights_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->grant('system', 'projektleitung', 'users-and-roles');

        $this->assertTrue($admin->can('update', [$admin, ['role' => UserRole::PROJEKTLEITUNG->value]]));
    }

    public function test_the_last_person_with_roles_manage_is_not_demoted_or_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $userManager = User::factory()->role(UserRole::KOORDINATION)->create();
        $this->grantRights('system', 'koordination', 'users-only', [Right::USERS_MANAGE]);

        $this->assertTrue(Rights::allows($userManager, Right::USERS_MANAGE));
        $this->assertFalse(Rights::allows($userManager, Right::ROLES_MANAGE));

        $this->assertFalse($userManager->can('update', [$admin, ['role' => UserRole::MITGLIED->value]]));
        $this->assertFalse($userManager->can('delete', $admin));

        User::factory()->admin()->create();

        $this->assertTrue($userManager->can('update', [$admin, ['role' => UserRole::MITGLIED->value]]));
        $this->assertTrue($userManager->can('delete', $admin));
    }

    public function test_an_account_that_waits_for_approval_does_not_count_as_keeper(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        User::factory()->admin()->create(['approved_at' => null]);

        $this->assertSame(1, Rights::countUsersWith(Right::ROLES_MANAGE, $admin));
        $this->assertTrue($admin->can('delete', $other));

        $other->delete();

        $this->assertSame(0, Rights::countUsersWith(Right::ROLES_MANAGE, $admin));
    }

    // Creator role and cache

    public function test_the_creator_gets_the_creator_role_of_the_settings(): void
    {
        $this->assertSame(ProjectRole::OWNER, AppSettings::projectCreatorRole());

        AppSettings::setProjectCreatorRole(ProjectRole::MANAGER);
        $creator = User::factory()->role(UserRole::KOORDINATION)->create();
        $this->actingAs($creator);

        $project = (new CreateProject)(null, ['name' => 'Made by a coordinator']);

        $this->assertSame(ProjectRole::MANAGER, $project->roleOf($creator));
    }

    public function test_the_rights_are_read_anew_for_every_queue_job(): void
    {
        $this->assertFalse(Rights::projectRoleAllows(ProjectRole::GUEST, Right::TASK_MOVE));

        // Changed behind the back of the cache, as another process would.
        $this->grant('project', 'guest', 'contribute', clearCache: false);
        $this->assertFalse(Rights::projectRoleAllows(ProjectRole::GUEST, Right::TASK_MOVE));

        event(new JobProcessing('sync', $this->createStub(Job::class)));

        $this->assertTrue(Rights::projectRoleAllows(ProjectRole::GUEST, Right::TASK_MOVE));
    }

    private function grant(string $scope, string $role, string $function, bool $clearCache = true): void
    {
        DB::table('role_functions')->insert([
            'role_id' => $this->roleId($scope, $role),
            'function_id' => DB::table('functions')->where(['scope' => $scope, 'key' => $function])->value('id'),
        ]);

        if ($clearCache) {
            Rights::clearCache();
        }
    }

    private function revoke(string $scope, string $role, string $function): void
    {
        DB::table('role_functions')
            ->where('role_id', $this->roleId($scope, $role))
            ->where('function_id', DB::table('functions')->where(['scope' => $scope, 'key' => $function])->value('id'))
            ->delete();

        Rights::clearCache();
    }

    /**
     * A function of its own (as made in the settings) for one role.
     *
     * @param  list<Right>  $rights
     */
    private function grantRights(string $scope, string $role, string $function, array $rights): void
    {
        $functionId = DB::table('functions')->insertGetId([
            'scope' => $scope,
            'key' => $function,
            'names' => json_encode(['de' => $function, 'en' => $function]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($rights as $right) {
            DB::table('function_rights')->insert(['function_id' => $functionId, 'right' => $right->value]);
        }

        $this->grant($scope, $role, $function);
    }

    private function roleId(string $scope, string $role): int
    {
        return (int) DB::table('roles')->where(['scope' => $scope, 'key' => $role])->value('id');
    }
}
