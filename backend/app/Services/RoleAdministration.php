<?php

namespace App\Services;

use App\Enums\Right;
use App\Models\Role;
use App\Models\RoleFunction;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Rights;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Roles and functions as the settings change them (/admin/roles, right
 * roles.manage, docs/rollen-funktionen-rechte.md §6).
 *
 * Every change runs in a transaction and is checked against the ground
 * rules afterwards (§7.1 to §7.3): somebody keeps users.manage and
 * roles.manage, nobody takes them away from themselves, and no project loses
 * its last holder. A change that breaks a rule is rolled back.
 *
 * Shipped roles and functions (config/permissions.php) can be changed and
 * reset, but not deleted. Their keys never change.
 */
final class RoleAdministration
{
    /** Rights that somebody must always keep, so the settings stay reachable. */
    private const PROTECTED_RIGHTS = [Right::USERS_MANAGE, Right::ROLES_MANAGE];

    private const KEY_LENGTH = 40;

    private const NAME_LENGTH = 60;

    /**
     * @param  array{de: string, en: string}  $names
     * @param  list<int|string>  $functionIds
     * @param  list<string>|null  $projectCap  Project rights a system role lets through, null = all
     */
    public function createRole(User $actor, string $scope, array $names, array $functionIds, ?array $projectCap = null): Role
    {
        return $this->changeRole($actor, function () use ($scope, $names, $functionIds, $projectCap): Role {
            $names = $this->names($names);

            $role = Role::create([
                'scope' => $scope,
                'key' => $this->freeKey('roles', $scope, $names['en'], 'role'),
                'names' => $names,
                'project_cap' => $this->cap($scope, $projectCap),
            ]);
            $role->functions()->sync($this->functionIds($scope, $functionIds));

            return $role;
        });
    }

    /**
     * Only the given parts change. "projectCap" present and null means: all
     * project rights.
     *
     * @param  array{names?: array{de: string, en: string}|null, functionIds?: list<int|string>|null, projectCap?: list<string>|null}  $input
     */
    public function updateRole(User $actor, int|string $id, array $input): Role
    {
        return $this->changeRole($actor, function () use ($id, $input): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            if (isset($input['names'])) {
                $role->names = $this->names($input['names']);
            }

            if (array_key_exists('projectCap', $input)) {
                $role->project_cap = $this->cap($role->scope, $input['projectCap']);
            }

            $role->save();

            if (isset($input['functionIds'])) {
                $role->functions()->sync($this->functionIds($role->scope, $input['functionIds']));
            }

            return $role;
        });
    }

    public function duplicateRole(User $actor, int|string $id): Role
    {
        return $this->changeRole($actor, function () use ($id): Role {
            $source = Role::query()->findOrFail($id);
            $names = $this->copyNames($source->names);

            $role = Role::create([
                'scope' => $source->scope,
                'key' => $this->freeKey('roles', $source->scope, $names['en'], 'role'),
                'names' => $names,
                'project_cap' => $source->project_cap,
            ]);
            $role->functions()->sync($source->functions()->pluck('functions.id')->all());

            return $role;
        });
    }

    /**
     * A role goes only when nobody has it, it is not the default and not the
     * role creators of projects get. Shipped roles stay.
     */
    public function deleteRole(User $actor, int|string $id): Role
    {
        return $this->changeRole($actor, function () use ($id): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            if ($role->is_standard) {
                throw ValidationException::withMessages(['id' => __('roles.standard_not_deletable')]);
            }

            if ($role->usage_count > 0) {
                throw ValidationException::withMessages(['id' => __('roles.role_in_use', ['count' => $role->usage_count])]);
            }

            if ($role->is_default) {
                throw ValidationException::withMessages(['id' => __('roles.default_not_deletable')]);
            }

            if ($role->scope === Role::SCOPE_PROJECT && AppSettings::projectCreatorRole()->is($role)) {
                throw ValidationException::withMessages(['id' => __('roles.creator_not_deletable')]);
            }

            $role->delete();

            return $role;
        });
    }

    /** The role new people (system) or new members (project) get: one per scope. */
    public function setDefaultRole(User $actor, int|string $id): Role
    {
        return $this->changeRole($actor, function () use ($id): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($id);

            Role::query()->where('scope', $role->scope)->whereKeyNot($role->id)->update(['is_default' => false]);
            $role->is_default = true;
            $role->save();

            return $role;
        });
    }

    /** Names, functions and cap of a shipped role back to config/permissions.php. */
    public function resetRole(User $actor, int|string $id): Role
    {
        return $this->changeRole($actor, function () use ($id): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($id);
            $standard = config("permissions.roles.{$role->scope}.{$role->key}");

            if (! is_array($standard)) {
                throw ValidationException::withMessages(['id' => __('roles.not_standard')]);
            }

            $role->names = $standard['names'];
            $role->project_cap = $standard['project_cap'] ?? null;
            $role->save();

            $role->functions()->sync(
                RoleFunction::query()
                    ->where('scope', $role->scope)
                    ->whereIn('key', $standard['functions'])
                    ->pluck('id')
                    ->all(),
            );

            return $role;
        });
    }

    /**
     * @param  array{de: string, en: string}  $names
     * @param  list<string>  $rights
     */
    public function createFunction(User $actor, string $scope, array $names, array $rights): RoleFunction
    {
        return $this->changeFunction($actor, function () use ($scope, $names, $rights): RoleFunction {
            $names = $this->names($names);

            $function = RoleFunction::create([
                'scope' => $scope,
                'key' => $this->freeKey('functions', $scope, $names['en'], 'function'),
                'names' => $names,
            ]);
            $this->writeRights($function, $rights);

            return $function;
        });
    }

    /**
     * @param  array{names?: array{de: string, en: string}|null, rights?: list<string>|null}  $input
     */
    public function updateFunction(User $actor, int|string $id, array $input): RoleFunction
    {
        return $this->changeFunction($actor, function () use ($id, $input): RoleFunction {
            $function = RoleFunction::query()->lockForUpdate()->findOrFail($id);

            if (isset($input['names'])) {
                $function->names = $this->names($input['names']);
            }

            // Also when only the rights change, so updated_at tells the last change.
            $function->touch();

            if (isset($input['rights'])) {
                $this->writeRights($function, $input['rights']);
            }

            return $function;
        });
    }

    public function duplicateFunction(User $actor, int|string $id): RoleFunction
    {
        return $this->changeFunction($actor, function () use ($id): RoleFunction {
            $source = RoleFunction::query()->findOrFail($id);
            $names = $this->copyNames($source->names);

            $function = RoleFunction::create([
                'scope' => $source->scope,
                'key' => $this->freeKey('functions', $source->scope, $names['en'], 'function'),
                'names' => $names,
            ]);
            $this->writeRights($function, array_map(fn (Right $right): string => $right->value, $source->rights));

            return $function;
        });
    }

    /** A function goes only when no role uses it. Shipped functions stay. */
    public function deleteFunction(User $actor, int|string $id): RoleFunction
    {
        return $this->changeFunction($actor, function () use ($id): RoleFunction {
            $function = RoleFunction::query()->lockForUpdate()->findOrFail($id);

            if ($function->is_standard) {
                throw ValidationException::withMessages(['id' => __('roles.standard_not_deletable')]);
            }

            if ($function->usage_count > 0) {
                throw ValidationException::withMessages(['id' => __('roles.function_in_use', ['count' => $function->usage_count])]);
            }

            $function->delete();

            return $function;
        });
    }

    /** Names and rights of a shipped function back to config/permissions.php. */
    public function resetFunction(User $actor, int|string $id): RoleFunction
    {
        return $this->changeFunction($actor, function () use ($id): RoleFunction {
            $function = RoleFunction::query()->lockForUpdate()->findOrFail($id);
            $standard = config("permissions.functions.{$function->scope}.{$function->key}");

            if (! is_array($standard)) {
                throw ValidationException::withMessages(['id' => __('roles.not_standard')]);
            }

            $function->names = $standard['names'];
            $function->touch();
            $this->writeRights($function, $standard['rights']);

            return $function;
        });
    }

    /**
     * @param  Closure(): Role  $write
     */
    private function changeRole(User $actor, Closure $write): Role
    {
        $role = $this->change($actor, $write);

        return $role->exists ? (Role::cached($role->id) ?? $role) : $role;
    }

    /**
     * @param  Closure(): RoleFunction  $write
     */
    private function changeFunction(User $actor, Closure $write): RoleFunction
    {
        $function = $this->change($actor, $write);

        return $function->exists ? $function->refresh() : $function;
    }

    /**
     * Write, then check the ground rules against the state before. Whatever
     * happens, the rights are read anew afterwards.
     *
     * @template T of Role|RoleFunction
     *
     * @param  Closure(): T  $write
     * @return T
     *
     * @throws ValidationException
     */
    private function change(User $actor, Closure $write): Role|RoleFunction
    {
        $before = $this->protectedState($actor);

        try {
            return DB::transaction(function () use ($actor, $write, $before): Role|RoleFunction {
                $model = $write();

                Rights::clearCache();
                $this->assertGroundRules($actor, $before);

                return $model;
            });
        } finally {
            Rights::clearCache();
        }
    }

    /**
     * @return array{own: list<Right>, counts: array<string, int>, held: list<int>}
     */
    private function protectedState(User $actor): array
    {
        $counts = [];
        foreach (self::PROTECTED_RIGHTS as $right) {
            $counts[$right->value] = Rights::countUsersWith($right);
        }

        return [
            'own' => array_values(array_filter(self::PROTECTED_RIGHTS, fn (Right $right): bool => Rights::allows($actor, $right))),
            'counts' => $counts,
            'held' => Rights::projectIdsWithHolder(),
        ];
    }

    /**
     * @param  array{own: list<Right>, counts: array<string, int>, held: list<int>}  $before
     *
     * @throws ValidationException
     */
    private function assertGroundRules(User $actor, array $before): void
    {
        foreach (self::PROTECTED_RIGHTS as $right) {
            if (in_array($right, $before['own'], true) && ! Rights::allows($actor, $right)) {
                throw ValidationException::withMessages(['rights' => __('roles.self_removal', ['right' => $right->label()])]);
            }

            if ($before['counts'][$right->value] > 0 && Rights::countUsersWith($right) === 0) {
                throw ValidationException::withMessages(['rights' => __('roles.last_holder', ['right' => $right->label()])]);
            }
        }

        $lost = count(array_diff($before['held'], Rights::projectIdsWithHolder()));

        if ($lost > 0) {
            throw ValidationException::withMessages(['rights' => __('roles.project_without_holder', ['count' => $lost])]);
        }
    }

    /**
     * @param  array{de?: mixed, en?: mixed}  $names
     * @return array{de: string, en: string}
     *
     * @throws ValidationException
     */
    private function names(array $names): array
    {
        $clean = [];

        foreach (['de', 'en'] as $locale) {
            $name = trim((string) ($names[$locale] ?? ''));

            if ($name === '' || mb_strlen($name) > self::NAME_LENGTH) {
                throw ValidationException::withMessages(["input.names.{$locale}" => __('roles.name_length', ['max' => self::NAME_LENGTH])]);
            }

            $clean[$locale] = $name;
        }

        return $clean;
    }

    /**
     * @param  array{de: string, en: string}  $names
     * @return array{de: string, en: string}
     */
    private function copyNames(array $names): array
    {
        $copy = [];

        foreach (['de', 'en'] as $locale) {
            $suffix = (string) __('roles.copy_suffix', [], $locale);
            $copy[$locale] = mb_substr($names[$locale] ?? '', 0, self::NAME_LENGTH - mb_strlen($suffix) - 1).' '.$suffix;
        }

        return $copy;
    }

    /**
     * The cap of a system role as stored: null = all project rights,
     * otherwise the values in the order of the enum. Project roles have none.
     *
     * @param  list<string>|null  $cap
     * @return list<string>|null
     *
     * @throws ValidationException
     */
    private function cap(string $scope, ?array $cap): ?array
    {
        if ($cap === null) {
            return null;
        }

        if ($scope !== Role::SCOPE_SYSTEM) {
            throw ValidationException::withMessages(['input.projectCap' => __('roles.cap_only_system')]);
        }

        return $this->rightValues(Right::SCOPE_PROJECT, $cap, 'input.projectCap');
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function functionIds(string $scope, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $found = RoleFunction::query()->where('scope', $scope)->whereIn('id', $ids)->pluck('id')->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['input.functionIds' => __('roles.function_other_scope')]);
        }

        return $ids;
    }

    /**
     * @param  list<string>  $rights
     */
    private function writeRights(RoleFunction $function, array $rights): void
    {
        $values = $this->rightValues($function->scope, $rights, 'input.rights');

        DB::table('function_rights')->where('function_id', $function->id)->delete();
        DB::table('function_rights')->insert(array_map(
            fn (string $value): array => ['function_id' => $function->id, 'right' => $value],
            $values,
        ));
    }

    /**
     * Known rights of the scope, without doubles, in the order of the enum.
     *
     * @param  list<string|Right>  $rights
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function rightValues(string $scope, array $rights, string $field): array
    {
        $given = [];

        foreach ($rights as $right) {
            $right = $right instanceof Right ? $right : Right::tryFrom((string) $right);

            if ($right === null || $right->scope() !== $scope) {
                throw ValidationException::withMessages([$field => __('roles.right_other_scope')]);
            }

            $given[] = $right->value;
        }

        return array_values(array_filter(
            array_map(fn (Right $right): string => $right->value, Right::ofScope($scope)),
            fn (string $value): bool => in_array($value, $given, true),
        ));
    }

    /** A key of the scope nobody uses yet, made from the English name. */
    private function freeKey(string $table, string $scope, string $name, string $fallback): string
    {
        $base = Str::limit(Str::slug($name), self::KEY_LENGTH - 4, '');
        $base = trim($base, '-') === '' ? $fallback : trim($base, '-');
        $key = $base;

        for ($number = 2; DB::table($table)->where(['scope' => $scope, 'key' => $key])->exists(); $number++) {
            $key = "{$base}-{$number}";
        }

        return $key;
    }
}
