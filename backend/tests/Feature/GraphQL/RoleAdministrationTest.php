<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\RoleFunction;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Roles and functions as the settings change them (right roles.manage):
 * the mutations, what they refuse, and the ground rules of
 * docs/rollen-funktionen-rechte.md §7.1 to §7.3 that no change may break.
 */
class RoleAdministrationTest extends GraphQLTestCase
{
    private const ROLE = 'id scope key names { de en } isDefault isStandard rights functions { key } projectCap usageCount';

    private const FUNCTION = 'id scope key names { de en } isStandard rights usageCount';

    private const CREATE_ROLE = 'mutation ($input: CreateRoleInput!) { createRole(input: $input) { '.self::ROLE.' } }';

    private const UPDATE_ROLE = 'mutation ($id: ID!, $input: UpdateRoleInput!) { updateRole(id: $id, input: $input) { '.self::ROLE.' } }';

    private const CREATE_FUNCTION = 'mutation ($input: CreateRoleFunctionInput!) { createRoleFunction(input: $input) { '.self::FUNCTION.' } }';

    private const UPDATE_FUNCTION = 'mutation ($id: ID!, $input: UpdateRoleFunctionInput!) { updateRoleFunction(id: $id, input: $input) { '.self::FUNCTION.' } }';

    // Reading

    public function test_roles_show_their_functions_and_cap(): void
    {
        $roles = collect($this->as('admin')->gql('{ roles { '.self::ROLE.' } }')->json('data.roles'))->keyBy(fn (array $role): string => $role['scope'].'/'.$role['key']);

        $this->assertSame(['plan', 'contribute'], array_column($roles['PROJECT/member']['functions'], 'key'));
        $this->assertNull($roles['SYSTEM/admin']['projectCap']);
        $this->assertSame([], $roles['SYSTEM/lesend']['projectCap']);
        $this->assertNull($roles['PROJECT/owner']['projectCap']);
        $this->assertSame(1, $roles['SYSTEM/admin']['usageCount']);
        $this->assertSame(1, $roles['PROJECT/manager']['usageCount']);
    }

    public function test_how_often_a_role_is_given_and_the_functions_are_for_role_managers_only(): void
    {
        $this->as('member')->gql('{ roles { key rights functions { key } } }')->assertJsonMissingPath('errors');

        $this->assertUnauthorized($this->as('member')->gql('{ roles { key usageCount } }'));
        $this->assertUnauthorized($this->as('owner')->gql('{ roleFunctions { key } }'));
    }

    public function test_the_rights_of_a_scope_are_listed_in_the_order_of_the_code(): void
    {
        $rights = $this->as('member')->gql('{ system: rights(scope: SYSTEM) project: rights(scope: PROJECT) }')->json('data');

        $this->assertSame(array_map(fn (Right $right): string => $right->name, Right::ofScope(Right::SCOPE_SYSTEM)), $rights['system']);
        $this->assertSame('PROJECT_UPDATE', $rights['project'][0]);
        $this->assertCount(25, $rights['project']);
    }

    public function test_functions_are_listed_by_scope_with_their_use(): void
    {
        $functions = collect($this->as('admin')->gql('{ roleFunctions(scope: PROJECT) { '.self::FUNCTION.' } }')->json('data.roleFunctions'))->keyBy('key');

        $this->assertSame(['plan', 'contribute', 'manage-tasks', 'manage-project', 'own-project', 'agent-work'], $functions->keys()->all());
        $this->assertSame(['PROJECT_DELETE', 'OWNER_MANAGE', 'TRASH_PURGE'], $functions['own-project']['rights']);
        $this->assertSame(1, $functions['own-project']['usageCount']);
        $this->assertSame(4, $functions['plan']['usageCount']);
        $this->assertTrue($functions['plan']['isStandard']);
    }

    // Roles

    public function test_a_role_of_ones_own_gets_a_key_and_gives_its_rights(): void
    {
        $role = $this->as('admin')->gql(self::CREATE_ROLE, ['input' => [
            'scope' => 'PROJECT',
            'names' => ['de' => ' Prüferin ', 'en' => 'Reviewer'],
            'functionIds' => [$this->functionId('project', 'contribute'), $this->functionId('project', 'manage-tasks')],
        ]])->assertJsonMissingPath('errors')->json('data.createRole');

        $this->assertSame('reviewer', $role['key']);
        $this->assertSame(['de' => 'Prüferin', 'en' => 'Reviewer'], $role['names']);
        $this->assertFalse($role['isStandard']);
        $this->assertFalse($role['isDefault']);
        $this->assertSame(['contribute', 'manage-tasks'], array_column($role['functions'], 'key'));
        $this->assertContains('TASK_DELETE', $role['rights']);
        $this->assertNotContains('MILESTONE_CREATE', $role['rights']);

        $this->as('owner')->gql('mutation ($p: ID!, $u: ID!) { setProjectMember(projectId: $p, userId: $u, role: "reviewer") { id } }', ['p' => $this->project->id, 'u' => $this->users['stranger']->id])
            ->assertJsonMissingPath('errors');

        $this->assertTrue($this->users['stranger']->can('delete', $this->task));
        $this->assertFalse($this->users['stranger']->can('createMilestone', $this->project));
    }

    public function test_a_second_role_with_the_same_name_gets_another_key(): void
    {
        $input = ['scope' => 'SYSTEM', 'names' => ['de' => 'Mitglied', 'en' => 'Mitglied'], 'functionIds' => []];

        $this->as('admin')->gql(self::CREATE_ROLE, ['input' => $input])->assertJsonPath('data.createRole.key', 'mitglied-2');
        $this->as('admin')->gql(self::CREATE_ROLE, ['input' => $input])->assertJsonPath('data.createRole.key', 'mitglied-3');
    }

    public function test_update_changes_only_what_is_given_and_never_the_key(): void
    {
        $id = Role::idOf(Role::SCOPE_PROJECT, ProjectRole::GUEST);

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => ['names' => ['de' => 'Zuschauer', 'en' => 'Viewer']]])
            ->assertJsonPath('data.updateRole.key', 'guest')
            ->assertJsonPath('data.updateRole.names.de', 'Zuschauer')
            ->assertJsonPath('data.updateRole.functions', []);

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => ['functionIds' => [$this->functionId('project', 'contribute')]]])
            ->assertJsonPath('data.updateRole.names.de', 'Zuschauer')
            ->assertJsonPath('data.updateRole.functions', [['key' => 'contribute']])
            ->assertJsonPath('data.updateRole.isStandard', true);
    }

    public function test_the_cap_of_a_system_role_limits_project_rights_at_once(): void
    {
        $id = Role::idOf(Role::SCOPE_SYSTEM, UserRole::MITGLIED);
        $this->assertTrue($this->users['manager']->can('update', $this->task));

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => ['projectCap' => ['TASK_MOVE', 'COMMENT_CREATE', 'PROJECT_DELETE']]])
            ->assertJsonPath('data.updateRole.projectCap', ['PROJECT_DELETE', 'TASK_MOVE', 'COMMENT_CREATE']);

        $this->assertFalse($this->users['manager']->can('update', $this->task));
        $this->assertTrue($this->users['manager']->can('move', $this->task));

        // Names alone leave the cap; null opens it again.
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => ['names' => ['de' => 'Mitglied', 'en' => 'Member']]])
            ->assertJsonPath('data.updateRole.projectCap', ['PROJECT_DELETE', 'TASK_MOVE', 'COMMENT_CREATE']);
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => ['projectCap' => null]])
            ->assertJsonPath('data.updateRole.projectCap', null);

        $this->assertTrue($this->users['manager']->can('update', $this->task));
    }

    public function test_scopes_do_not_mix(): void
    {
        $this->assertValidation('input.functionIds', $this->as('admin')->gql(self::CREATE_ROLE, ['input' => [
            'scope' => 'PROJECT', 'names' => ['de' => 'Mix', 'en' => 'Mix'], 'functionIds' => [$this->functionId('system', 'status')],
        ]]));
        $this->assertValidation('input.projectCap', $this->as('admin')->gql(self::CREATE_ROLE, ['input' => [
            'scope' => 'PROJECT', 'names' => ['de' => 'Mix', 'en' => 'Mix'], 'functionIds' => [], 'projectCap' => [],
        ]]));
        $this->assertValidation('input.projectCap', $this->as('admin')->gql(self::UPDATE_ROLE, [
            'id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::LESEND), 'input' => ['projectCap' => ['USERS_MANAGE']],
        ]));
        $this->assertValidation('input.rights', $this->as('admin')->gql(self::CREATE_FUNCTION, ['input' => [
            'scope' => 'PROJECT', 'names' => ['de' => 'Mix', 'en' => 'Mix'], 'rights' => ['TASK_MOVE', 'USERS_MANAGE'],
        ]]));
        // A name of blanks arrives as null (TrimStrings) and fails as a missing name.
        $this->assertNotEmpty($this->as('admin')->gql(self::CREATE_FUNCTION, ['input' => [
            'scope' => 'PROJECT', 'names' => ['de' => 'Leer', 'en' => '  '], 'rights' => [],
        ]])->json('errors'));
        $this->assertValidation('input.names.de', $this->as('admin')->gql(self::CREATE_FUNCTION, ['input' => [
            'scope' => 'PROJECT', 'names' => ['de' => str_repeat('x', 61), 'en' => 'Long'], 'rights' => [],
        ]]));

        $this->assertSame(10, Role::query()->count());
        $this->assertSame(15, RoleFunction::query()->count());
    }

    public function test_duplicate_copies_functions_and_cap_as_a_role_of_ones_own(): void
    {
        $this->as('admin')->gql('mutation ($id: ID!) { duplicateRole(id: $id) { '.self::ROLE.' } }', ['id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::LESEND)])
            ->assertJsonPath('data.duplicateRole.key', 'read-only-copy')
            ->assertJsonPath('data.duplicateRole.names', ['de' => 'Lesend (Kopie)', 'en' => 'Read only (copy)'])
            ->assertJsonPath('data.duplicateRole.isStandard', false)
            ->assertJsonPath('data.duplicateRole.isDefault', false)
            ->assertJsonPath('data.duplicateRole.functions', [['key' => 'status']])
            ->assertJsonPath('data.duplicateRole.projectCap', [])
            ->assertJsonPath('data.duplicateRole.usageCount', 0);
    }

    public function test_a_role_is_deleted_only_when_it_is_free(): void
    {
        $delete = 'mutation ($id: ID!) { deleteRole(id: $id) { id key } }';

        $this->as('admin')->gql($delete, ['id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::GUEST)])
            ->assertJsonPath('errors.0.extensions.validation.id.0', __('roles.standard_not_deletable'));

        $own = $this->ownRole('project', 'reviewer', ['contribute']);
        $this->project->members()->attach($this->users['stranger']->id, ['role_id' => $own->id]);
        $this->as('admin')->gql($delete, ['id' => $own->id])
            ->assertJsonPath('errors.0.extensions.validation.id.0', __('roles.role_in_use', ['count' => 1]));

        $this->project->members()->detach($this->users['stranger']->id);
        $this->as('admin')->gql('mutation ($id: ID!) { setDefaultRole(id: $id) { id } }', ['id' => $own->id])->assertJsonMissingPath('errors');
        $this->as('admin')->gql($delete, ['id' => $own->id])
            ->assertJsonPath('errors.0.extensions.validation.id.0', __('roles.default_not_deletable'));

        $this->as('admin')->gql('mutation ($id: ID!) { setDefaultRole(id: $id) { id } }', ['id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::MEMBER)]);
        $this->as('admin')->gql($delete, ['id' => $own->id])->assertJsonPath('data.deleteRole.key', 'reviewer');

        $this->assertNull(Role::byKey(Role::SCOPE_PROJECT, 'reviewer'));
        $this->assertDatabaseMissing('role_functions', ['role_id' => $own->id]);
    }

    public function test_there_is_one_default_role_per_scope_and_new_people_get_it(): void
    {
        $default = 'mutation ($id: ID!) { setDefaultRole(id: $id) { key isDefault } }';

        $this->as('admin')->gql($default, ['id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::KOORDINATION)])
            ->assertJsonPath('data.setDefaultRole', ['key' => 'koordination', 'isDefault' => true]);

        $this->assertSame(['koordination'], Role::query()->where(['scope' => 'system', 'is_default' => true])->pluck('key')->all());
        $this->assertSame(['member'], Role::query()->where(['scope' => 'project', 'is_default' => true])->pluck('key')->all());

        $this->as('admin')->gql('mutation { createUser(input: {name: "Fritz", email: "fritz@example.com"}) { role { key } } }')
            ->assertJsonPath('data.createUser.role.key', 'koordination');
    }

    public function test_reset_brings_a_shipped_role_back_to_the_standard(): void
    {
        $id = Role::idOf(Role::SCOPE_SYSTEM, UserRole::LESEND);
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $id, 'input' => [
            'names' => ['de' => 'Nur schauen', 'en' => 'Look only'],
            'functionIds' => [$this->functionId('system', 'status'), $this->functionId('system', 'create-projects')],
            'projectCap' => null,
        ]])->assertJsonPath('data.updateRole.projectCap', null);

        $this->as('admin')->gql('mutation ($id: ID!) { resetRole(id: $id) { '.self::ROLE.' } }', ['id' => $id])
            ->assertJsonPath('data.resetRole.names', ['de' => 'Lesend', 'en' => 'Read only'])
            ->assertJsonPath('data.resetRole.functions', [['key' => 'status']])
            ->assertJsonPath('data.resetRole.projectCap', []);

        $this->assertValidation('id', $this->as('admin')->gql('mutation ($id: ID!) { resetRole(id: $id) { id } }', ['id' => $this->ownRole('system', 'clerk', [])->id]));
    }

    // Functions

    public function test_a_function_of_ones_own_bundles_rights_of_its_scope(): void
    {
        $function = $this->as('admin')->gql(self::CREATE_FUNCTION, ['input' => [
            'scope' => 'PROJECT',
            'names' => ['de' => 'Aufräumen', 'en' => 'Tidy up'],
            'rights' => ['TASK_DELETE', 'TODO_DELETE', 'TASK_DELETE'],
        ]])->assertJsonMissingPath('errors')->json('data.createRoleFunction');

        $this->assertSame('tidy-up', $function['key']);
        $this->assertSame(['TODO_DELETE', 'TASK_DELETE'], $function['rights']);
        $this->assertSame(0, $function['usageCount']);
        $this->assertFalse($function['isStandard']);
    }

    public function test_changed_rights_of_a_function_hold_at_once_for_every_role_that_uses_it(): void
    {
        $this->assertFalse($this->users['member']->can('delete', $this->task));

        $this->as('admin')->gql(self::UPDATE_FUNCTION, ['id' => $this->functionId('project', 'plan'), 'input' => [
            'rights' => ['MILESTONE_CREATE', 'TASK_DELETE'],
        ]])
            ->assertJsonPath('data.updateRoleFunction.rights', ['MILESTONE_CREATE', 'TASK_DELETE'])
            ->assertJsonPath('data.updateRoleFunction.names.de', 'Planen');

        $this->assertTrue($this->users['member']->can('delete', $this->task));
        $this->assertFalse($this->users['member']->can('createTodo', $this->milestone));
    }

    public function test_duplicate_and_delete_of_functions(): void
    {
        $delete = 'mutation ($id: ID!) { deleteRoleFunction(id: $id) { key } }';
        $guest = Role::idOf(Role::SCOPE_PROJECT, ProjectRole::GUEST);

        $copy = $this->as('admin')->gql('mutation ($id: ID!) { duplicateRoleFunction(id: $id) { '.self::FUNCTION.' } }', ['id' => $this->functionId('project', 'own-project')])
            ->assertJsonPath('data.duplicateRoleFunction.key', 'own-project-copy')
            ->assertJsonPath('data.duplicateRoleFunction.rights', ['PROJECT_DELETE', 'OWNER_MANAGE', 'TRASH_PURGE'])
            ->assertJsonPath('data.duplicateRoleFunction.usageCount', 0)
            ->json('data.duplicateRoleFunction');

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $guest, 'input' => ['functionIds' => [$copy['id']]]])->assertJsonMissingPath('errors');
        $this->as('admin')->gql($delete, ['id' => $copy['id']])
            ->assertJsonPath('errors.0.extensions.validation.id.0', __('roles.function_in_use', ['count' => 1]));

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $guest, 'input' => ['functionIds' => []]])->assertJsonMissingPath('errors');
        $this->as('admin')->gql($delete, ['id' => $copy['id']])->assertJsonPath('data.deleteRoleFunction.key', 'own-project-copy');
        $this->assertDatabaseMissing('function_rights', ['function_id' => $copy['id']]);

        $this->as('admin')->gql($delete, ['id' => $this->functionId('project', 'plan')])
            ->assertJsonPath('errors.0.extensions.validation.id.0', __('roles.standard_not_deletable'));
    }

    public function test_reset_brings_a_shipped_function_back_to_the_standard(): void
    {
        $id = $this->functionId('system', 'status');
        $this->as('admin')->gql(self::UPDATE_FUNCTION, ['id' => $id, 'input' => ['names' => ['de' => 'Lage', 'en' => 'State'], 'rights' => ['SYSTEM_VIEW', 'PROJECTS_CREATE']]]);
        $this->assertTrue(Rights::allows($this->users['lesend'], Right::PROJECTS_CREATE));

        $this->as('admin')->gql('mutation ($id: ID!) { resetRoleFunction(id: $id) { '.self::FUNCTION.' } }', ['id' => $id])
            ->assertJsonPath('data.resetRoleFunction.names', ['de' => 'Status', 'en' => 'Status'])
            ->assertJsonPath('data.resetRoleFunction.rights', ['SYSTEM_VIEW']);

        $this->assertFalse(Rights::allows($this->users['lesend'], Right::PROJECTS_CREATE));
    }

    // Ground rules (§7.1 to §7.3)

    public function test_nobody_takes_roles_manage_or_users_manage_away_from_themselves(): void
    {
        $response = $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::ADMIN), 'input' => [
            'functionIds' => [$this->functionId('system', 'status')],
        ]]);

        $this->assertValidation('rights', $response);
        $this->assertTrue(Rights::allows($this->users['admin'], Right::ROLES_MANAGE));
        $this->assertCount(9, Role::resolveOrFail(Role::SCOPE_SYSTEM, UserRole::ADMIN)->functions()->get());

        $this->assertValidation('rights', $this->as('admin')->gql(self::UPDATE_FUNCTION, ['id' => $this->functionId('system', 'users-and-roles'), 'input' => ['rights' => ['USERS_MANAGE']]]));
        $this->assertTrue(Rights::allows($this->users['admin'], Right::ROLES_MANAGE));
    }

    public function test_somebody_always_keeps_users_manage(): void
    {
        // A clerk who manages roles through a function of their own, so the
        // change below takes nothing away from the clerk.
        $rolesOnly = RoleFunction::create(['scope' => 'system', 'key' => 'roles-only', 'names' => ['de' => 'Rollen', 'en' => 'Roles']]);
        DB::table('function_rights')->insert(['function_id' => $rolesOnly->id, 'right' => Right::ROLES_MANAGE->value]);
        $this->users['clerk'] = User::factory()->role($this->ownRole('system', 'clerk', ['roles-only']))->create();

        $response = $this->as('clerk')->gql(self::UPDATE_FUNCTION, ['id' => $this->functionId('system', 'users-and-roles'), 'input' => ['rights' => ['ROLES_MANAGE']]]);

        $response->assertJsonPath('errors.0.extensions.validation.rights.0', __('roles.last_holder', ['right' => Right::USERS_MANAGE->label()]));
        $this->assertTrue(Rights::allows($this->users['admin'], Right::USERS_MANAGE));
    }

    public function test_no_project_loses_its_last_holder_through_a_changed_role(): void
    {
        $owner = Role::idOf(Role::SCOPE_PROJECT, ProjectRole::OWNER);
        $withoutOwnership = ['functionIds' => [$this->functionId('project', 'plan'), $this->functionId('project', 'contribute')]];

        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $owner, 'input' => $withoutOwnership])
            ->assertJsonPath('errors.0.extensions.validation.rights.0', __('roles.project_without_holder', ['count' => 1]));
        $this->assertSame(1, Rights::countHolders($this->project));

        // The cap of the owner's system role would do the same.
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => Role::idOf(Role::SCOPE_SYSTEM, UserRole::MITGLIED), 'input' => ['projectCap' => []]])
            ->assertJsonPath('errors.0.extensions.validation.rights.0', __('roles.project_without_holder', ['count' => 1]));

        // Once managers hold projects too, the owner role may lose it.
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => Role::idOf(Role::SCOPE_PROJECT, ProjectRole::MANAGER), 'input' => [
            'functionIds' => [$this->functionId('project', 'manage-project'), $this->functionId('project', 'own-project')],
        ]])->assertJsonMissingPath('errors');
        $this->as('admin')->gql(self::UPDATE_ROLE, ['id' => $owner, 'input' => $withoutOwnership])->assertJsonMissingPath('errors');

        $this->assertFalse(Rights::holdsProject($this->users['owner'], $this->projectRole(ProjectRole::OWNER)));
        $this->assertSame(1, Rights::countHolders($this->project));
    }

    private function assertValidation(string $field, TestResponse $response): void
    {
        $this->assertNotEmpty($response->json('errors.0.extensions.validation')[$field] ?? null, 'No validation error for '.$field.': '.mb_substr((string) json_encode($response->json('errors.0.message')), 0, 300));
    }

    private function functionId(string $scope, string $key): int
    {
        return (int) DB::table('functions')->where(['scope' => $scope, 'key' => $key])->value('id');
    }

    /**
     * @param  list<string>  $functions
     */
    private function ownRole(string $scope, string $key, array $functions): Role
    {
        $role = Role::create(['scope' => $scope, 'key' => $key, 'names' => ['de' => $key, 'en' => $key]]);
        $role->functions()->sync(array_map(fn (string $function): int => $this->functionId($scope, $function), $functions));
        Rights::clearCache();

        return Role::resolveOrFail($scope, $key);
    }
}
