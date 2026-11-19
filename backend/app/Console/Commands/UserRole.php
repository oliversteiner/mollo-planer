<?php

namespace App\Console\Commands;

use App\Console\Concerns\ManagesUserAccounts;
use App\Enums\Right;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Arr;

use function Laravel\Prompts\select;

/**
 * Shows or sets the system role of a user. The rules are the ones of the app (updateUser): somebody keeps
 * users.manage and roles.manage, and no project is left without a member who may delete it. Nobody is acting
 * here, so "not taking it away from yourself" does not apply.
 */
#[Signature('app:user-role {user : E-mail address or id of the user}
    {role? : Key of a system role (without it you choose)}')]
#[Description('Set the system role of a user (shows the old and the new role)')]
class UserRole extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        $role = $this->chooseRole($user);

        if ($role === null) {
            return self::FAILURE;
        }

        $old = $user->role;

        if ($old->is($role)) {
            $this->components->info(__('console.user_role.nothing', ['name' => $user->name, 'role' => $role->key]));

            return self::SUCCESS;
        }

        if (! $this->keepsTheRules($user, $role)) {
            return self::FAILURE;
        }

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.user_role.confirm', ['name' => $user->name, 'role' => $role->key]))) {
            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->logIntervention($user, 'role', ['from' => $old->key, 'to' => $role->key]);
        $this->components->info(__('console.user_role.done', ['name' => $user->name, 'old' => $old->key, 'new' => $role->key]));

        return self::SUCCESS;
    }

    private function chooseRole(User $user): ?Role
    {
        $roles = collect(Role::ofScope(Role::SCOPE_SYSTEM))->keyBy('key');
        $typed = $this->argument('role');

        if ($typed === null) {
            if (! $this->input->isInteractive()) {
                $this->components->error(__('console.user_role.needs_interaction', ['roles' => $roles->keys()->implode(', ')]));

                return null;
            }

            return $roles->get((string) select(
                label: __('console.user_role.choose', ['name' => $user->name, 'role' => $user->role->key]),
                options: $roles->keys()->all(),
                default: $user->role?->key,
            ));
        }

        $role = $roles->get(trim((string) $typed));

        if ($role === null) {
            $this->components->error(__('console.user_role.unknown', ['role' => $typed, 'roles' => $roles->keys()->implode(', ')]));
        }

        return $role;
    }

    /** The last person with users.manage or roles.manage keeps it, and no project loses its last holder. */
    private function keepsTheRules(User $user, Role $role): bool
    {
        $newRights = Rights::rightsOfRole($role);

        foreach ([Right::USERS_MANAGE, Right::ROLES_MANAGE] as $right) {
            if (Rights::allows($user, $right) && ! in_array($right, $newRights, true) && Rights::countUsersWith($right, $user) === 0) {
                $this->components->error(__('roles.last_holder', ['right' => $right->label()]));

                return false;
            }
        }

        $projects = array_map(fn ($project): string => $project->name, Rights::projectsOnlyHeldBy($user, $role));

        if ($projects !== []) {
            $this->components->error(__('projects.only_holder', [
                'name' => $user->name,
                'projects' => Arr::join($projects, ', ', __('projects.list_and')),
                'right' => Right::PROJECT_DELETE->label(),
            ]));

            return false;
        }

        return true;
    }
}
