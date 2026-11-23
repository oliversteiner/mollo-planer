<?php

namespace Tests\Feature;

use App\Console\Commands\PermissionsSyncDefaults;
use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Role;
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
        $this->assertSame(9, DB::table('functions')->where('scope', 'system')->count());
        $this->assertSame(6, DB::table('functions')->where('scope', 'project')->count());
        $this->assertSame(5, DB::table('roles')->where('scope', 'system')->count());
        $this->assertSame(5, DB::table('roles')->where('scope', 'project')->count());
    }

    public function test_changing_a_function_changes_the_rights_of_its_roles(): void
    {
        $member = User::factory()->create(['role' => UserRole::MITGLIED]);
        $this->assertFalse(Rights::allows($member, Right::USERS_MANAGE));

        $mitglied = DB::table('roles')->where(['scope' => 'system', 'key' => 'mitglied'])->value('id');
        $benutzer = DB::table('functions')->where(['scope' => 'system', 'key' => 'users-and-roles'])->value('id');
        DB::table('role_functions')->insert(['role_id' => $mitglied, 'function_id' => $benutzer]);
        Rights::clearCache();

        $this->assertTrue(Rights::allows($member, Right::USERS_MANAGE));
    }

    public function test_project_cap_none_blocks_project_rights_of_an_owner(): void
    {
        $user = User::factory()->create(['role' => UserRole::LESEND]);
        $project = Project::factory()->create();
        $project->members()->attach($user, ['role_id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::OWNER)]);

        $this->assertFalse(Rights::allows($user, Right::TASK_MOVE, $project));
        $this->assertSame([], Rights::rightsOf($user, $project));
    }

    public function test_project_manage_any_overrides_the_cap(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $project = Project::factory()->create();

        $this->assertTrue(Rights::allows($admin, Right::PROJECT_DELETE, $project));
    }

    public function test_the_standard_rows_can_be_restored(): void
    {
        DB::table('role_functions')->delete();
        RightsDefaults::apply();

        $this->assertTrue(Rights::projectRoleAllows($this->projectRole(ProjectRole::MEMBER), Right::TASK_MOVE));
        $this->assertFalse(Rights::projectRoleAllows($this->projectRole(ProjectRole::GUEST), Right::TASK_CREATE));
    }

    public function test_sync_command_restores_the_standard_rows(): void
    {
        DB::table('role_functions')->delete();

        $this->artisan('permissions:sync-defaults')->assertSuccessful();

        $this->assertTrue(Rights::projectRoleAllows($this->projectRole(ProjectRole::MEMBER), Right::TASK_MOVE));
    }

    public function test_check_reports_a_missing_standard_role_and_changes_nothing(): void
    {
        $this->artisan('permissions:sync-defaults --check')->assertSuccessful();

        DB::table('roles')->where(['scope' => 'project', 'key' => ProjectRole::AGENT->value])->delete();

        $this->artisan('permissions:sync-defaults --check')
            ->expectsOutputToContain('project.role.agent')
            ->assertExitCode(PermissionsSyncDefaults::EXIT_MISSING);
        $this->assertDatabaseMissing('roles', ['scope' => 'project', 'key' => ProjectRole::AGENT->value]);

        $this->artisan('permissions:sync-defaults')->assertSuccessful();
        $this->artisan('permissions:sync-defaults --check')->assertSuccessful();
    }
}
