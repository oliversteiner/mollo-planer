<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Writes the standard rights from config/permissions.php into the tables.
 * Standard entries are reset to the file; roles and functions that are not
 * in the file (made in the settings later) stay untouched.
 */
final class RightsDefaults
{
    public static function apply(): void
    {
        DB::transaction(function (): void {
            foreach ((array) config('permissions.functions') as $scope => $functions) {
                foreach ($functions as $key => $function) {
                    $functionId = self::upsertFunction($scope, $key, $function['names']);
                    DB::table('function_rights')->where('function_id', $functionId)->delete();
                    foreach ($function['rights'] as $right) {
                        DB::table('function_rights')->insert(['function_id' => $functionId, 'right' => $right]);
                    }
                }
            }

            foreach ((array) config('permissions.roles') as $scope => $roles) {
                foreach ($roles as $key => $role) {
                    $roleId = self::upsertRole($scope, $key, $role);
                    DB::table('role_functions')->where('role_id', $roleId)->delete();
                    foreach ($role['functions'] as $functionKey) {
                        $functionId = DB::table('functions')->where(['scope' => $scope, 'key' => $functionKey])->value('id');
                        DB::table('role_functions')->insert(['role_id' => $roleId, 'function_id' => $functionId]);
                    }
                }
            }
        });

        Rights::clearCache();
    }

    /** @param  array{de: string, en: string}  $names */
    private static function upsertFunction(string $scope, string $key, array $names): int
    {
        $values = ['names' => json_encode($names, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        $existing = DB::table('functions')->where(['scope' => $scope, 'key' => $key])->value('id');
        if ($existing !== null) {
            DB::table('functions')->where('id', $existing)->update($values);

            return (int) $existing;
        }

        return (int) DB::table('functions')->insertGetId([
            'scope' => $scope,
            'key' => $key,
            'created_at' => now(),
            ...$values,
        ]);
    }

    /**
     * @param  array{names: array{de: string, en: string}, project_cap?: list<string>|null}  $role
     */
    private static function upsertRole(string $scope, string $key, array $role): int
    {
        $values = [
            'names' => json_encode($role['names'], JSON_UNESCAPED_UNICODE),
            'project_cap' => array_key_exists('project_cap', $role) && $role['project_cap'] !== null
                ? json_encode($role['project_cap'])
                : null,
            'updated_at' => now(),
        ];
        $existing = DB::table('roles')->where(['scope' => $scope, 'key' => $key])->value('id');
        if ($existing !== null) {
            DB::table('roles')->where('id', $existing)->update($values);

            return (int) $existing;
        }

        return (int) DB::table('roles')->insertGetId([
            'scope' => $scope,
            'key' => $key,
            'created_at' => now(),
            ...$values,
        ]);
    }
}
