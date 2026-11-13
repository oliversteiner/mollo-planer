<?php

namespace Tests\Feature;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
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

        $this->assertSame(ProjectRole::OWNER, $project->roleOf($owner));
        $this->assertSame(ProjectRole::MANAGER, $project->roleOf($manager));
        $this->assertSame(ProjectRole::MEMBER, $project->roleOf($member));
        $this->assertNull($project->roleOf($stranger));
    }

    public function test_role_in_one_project_grants_nothing_in_another(): void
    {
        $owner = User::factory()->create();
        Project::factory()->withMember($owner, ProjectRole::OWNER)->create();
        $other = Project::factory()->create();

        $this->assertFalse(Rights::projectAllows($owner, $other, ProjectAbility::MILESTONE_CREATE));
        $this->assertSame([], Rights::projectRightsOf($owner, $other));
    }

    public function test_member_and_manager_get_abilities_from_the_table(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $project = Project::factory()
            ->withMember($manager, ProjectRole::MANAGER)
            ->withMember($member)
            ->create();

        $this->assertTrue(Rights::projectAllows($member, $project, ProjectAbility::TASK_MOVE));
        $this->assertFalse(Rights::projectAllows($member, $project, ProjectAbility::TASK_CREATE));
        $this->assertTrue(Rights::projectAllows($manager, $project, ProjectAbility::TASK_CREATE));
        $this->assertFalse(Rights::projectAllows($manager, $project, ProjectAbility::PROJECT_DELETE));
        $this->assertSame(
            Rights::projectRightsOfRole(ProjectRole::MANAGER),
            Rights::projectRightsOf($manager, $project),
        );
    }

    public function test_admin_has_every_ability_without_membership(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        foreach (ProjectAbility::cases() as $ability) {
            $this->assertTrue(Rights::projectAllows($admin, $project, $ability));
        }

        $this->assertSame(ProjectAbility::cases(), Rights::projectRightsOf($admin, $project));
    }

    public function test_loaded_members_avoid_extra_queries(): void
    {
        $member = User::factory()->create();
        $project = Project::factory()->withMember($member)->create()->load('members');

        Rights::projectRightsOf($member, $project);

        DB::enableQueryLog();
        Rights::projectRightsOf($member, $project);

        $this->assertSame([], DB::getQueryLog());
    }
}
