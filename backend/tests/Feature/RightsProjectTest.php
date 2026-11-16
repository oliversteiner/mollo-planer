<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RightsProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_of_a_member_is_read_from_the_membership(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();

        $project = Project::factory()
            ->withMember($owner, ProjectRole::OWNER)
            ->withMember($manager, ProjectRole::MANAGER)
            ->withMember($member)
            ->create();

        $this->assertSame(ProjectRole::OWNER->value, $project->roleOf($owner)?->key);
        $this->assertSame(ProjectRole::MANAGER->value, $project->roleOf($manager)?->key);
        $this->assertSame(ProjectRole::MEMBER->value, $project->roleOf($member)?->key);
        $this->assertNull($project->roleOf($stranger));
    }

    public function test_role_in_one_project_grants_nothing_in_another(): void
    {
        $owner = User::factory()->create();
        Project::factory()->withMember($owner, ProjectRole::OWNER)->create();
        $other = Project::factory()->create();

        $this->assertFalse(Rights::allows($owner, Right::MILESTONE_CREATE, $other));
        $this->assertSame([], Rights::rightsOf($owner, $other));
    }

    public function test_member_and_manager_get_abilities_from_the_table(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()
            ->withMember($manager, ProjectRole::MANAGER)
            ->withMember($member)
            ->create();

        $this->assertTrue(Rights::allows($member, Right::TASK_MOVE, $project));
        $this->assertTrue(Rights::allows($member, Right::TASK_CREATE, $project));
        $this->assertFalse(Rights::allows($member, Right::TASK_UPDATE, $project));
        $this->assertTrue(Rights::allows($manager, Right::TASK_CREATE, $project));
        $this->assertFalse(Rights::allows($manager, Right::PROJECT_DELETE, $project));
        $this->assertSame(
            Rights::projectRightsOfRole($this->projectRole(ProjectRole::MANAGER)),
            Rights::rightsOf($manager, $project),
        );
    }

    public function test_admin_has_every_ability_without_membership(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        foreach (Right::ofScope(Right::SCOPE_PROJECT) as $ability) {
            $this->assertTrue(Rights::allows($admin, $ability, $project));
        }

        $this->assertSame(Right::ofScope(Right::SCOPE_PROJECT), Rights::rightsOf($admin, $project));
    }

    public function test_loaded_members_avoid_extra_queries(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->withMember($member)->create()->load('members');

        Rights::rightsOf($member, $project);

        DB::enableQueryLog();
        Rights::rightsOf($member, $project);

        $this->assertSame([], DB::getQueryLog());
    }
}
