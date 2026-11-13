<?php

namespace Tests\Feature;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use App\Support\RightsDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RightsDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_rows_are_written_from_the_config(): void
    {
        $this->assertSame(5, DB::table('functions')->where('scope', 'system')->count());
        $this->assertSame(7, DB::table('functions')->where('scope', 'project')->count());
        $this->assertSame(5, DB::table('roles')->where('scope', 'system')->count());
        $this->assertSame(4, DB::table('roles')->where('scope', 'project')->count());
    }

    public function test_changing_a_function_changes_the_rights_of_its_roles(): void
    {
        $member = User::factory()->create(['role' => UserRole::MITGLIED]);
        $this->assertFalse(Rights::systemAllows($member, SystemAbility::USERS_MANAGE));

        $mitglied = DB::table('roles')->where(['scope' => 'system', 'key' => 'mitglied'])->value('id');
        $benutzer = DB::table('functions')->where(['scope' => 'system', 'key' => 'benutzer'])->value('id');
        DB::table('role_functions')->insert(['role_id' => $mitglied, 'function_id' => $benutzer]);
        Rights::clearCache();

        $this->assertTrue(Rights::systemAllows($member, SystemAbility::USERS_MANAGE));
    }

    public function test_project_cap_none_blocks_project_rights_of_an_owner(): void
    {
        $user = User::factory()->create(['role' => UserRole::LESEND]);
        $project = Project::factory()->create();
        $project->members()->attach($user, ['role' => ProjectRole::OWNER->value]);

        $this->assertFalse(Rights::projectAllows($user, $project, ProjectAbility::TASK_MOVE));
        $this->assertSame([], Rights::projectRightsOf($user, $project));
    }

    public function test_project_manage_any_overrides_the_cap(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $project = Project::factory()->create();

        $this->assertTrue(Rights::projectAllows($admin, $project, ProjectAbility::PROJECT_DELETE));
    }

    public function test_the_standard_rows_can_be_restored(): void
    {
        DB::table('role_functions')->delete();
        RightsDefaults::apply();

        $this->assertTrue(Rights::projectRoleAllows(ProjectRole::MEMBER, ProjectAbility::TASK_MOVE));
        $this->assertFalse(Rights::projectRoleAllows(ProjectRole::GUEST, ProjectAbility::TASK_CREATE));
    }

    public function test_sync_command_restores_the_standard_rows(): void
    {
        DB::table('role_functions')->delete();

        $this->artisan('permissions:sync-defaults')->assertSuccessful();

        $this->assertTrue(Rights::projectRoleAllows(ProjectRole::MEMBER, ProjectAbility::TASK_MOVE));
    }
}
