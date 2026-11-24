<?php

namespace App\Core\Rights;

use App\Support\Rights;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Brings only what is NEW in config/permissions.php into the rights tables and overwrites nothing (Auftrag 40 B). The reset
 * (App\Support\RightsDefaults::apply, `permissions:sync-defaults`) puts the standard back to the file; this one is safe to run
 * on every deployment.
 *
 * - A standard function or role that has no row is created with its standard rights or functions.
 * - For an existing one, names and assignments stay. A pair (right in a function, function in a role) that the file has and that
 *   was NEVER offered before is added. "Offered" is kept in the table rights_defaults_applied: a pair that was offered and
 *   is missing today was removed on purpose in the settings and is not added again.
 * - The first run on an existing database records the standard pairs that are there today as offered; what is missing
 *   then is new and is added.
 * - Nothing is ever removed. Functions and roles that are not in the file are only reported.
 *
 * Everything in one transaction, then the rights cache is cleared.
 */
final class DefaultsAddMissing
{
    public const FUNCTION_RIGHT = 'function_right';

    public const ROLE_FUNCTION = 'role_function';

    /**
     * What a run would add, nothing is written.
     *
     * @return list<string> one line per entry, for example "project.function.contribute + document.update"
     */
    public static function plan(): array
    {
        return self::run(write: false);
    }

    /**
     * Adds what is missing and records the offered pairs.
     *
     * @return list<string> one line per added entry
     */
    public static function apply(): array
    {
        $added = DB::transaction(fn (): array => self::run(write: true));

        foreach ($added as $entry) {
            Log::info('Standard rights added: '.$entry);
        }

        Rights::clearCache();

        return $added;
    }

    /**
     * Writes every pair of the file as offered (after the reset, which has just put all of them in place).
     */
    public static function recordAll(): void
    {
        if (! Schema::hasTable('rights_defaults_applied')) {
            return;
        }

        foreach ((array) config('permissions.functions') as $scope => $functions) {
            foreach ($functions as $key => $function) {
                foreach ($function['rights'] as $right) {
                    self::record(self::FUNCTION_RIGHT, $scope, $key, $right);
                }
            }
        }

        foreach ((array) config('permissions.roles') as $scope => $roles) {
            foreach ($roles as $key => $role) {
                foreach ($role['functions'] as $functionKey) {
                    self::record(self::ROLE_FUNCTION, $scope, $key, $functionKey);
                }
            }
        }
    }

    /**
     * Functions and roles in the tables that the file does not know (made in the settings). Reported, never touched.
     *
     * @return list<string> entries like "project.role.guest-plus"
     */
    public static function notInTheFile(): array
    {
        $extra = [];

        foreach (['functions' => 'function', 'roles' => 'role'] as $table => $label) {
            foreach (DB::table($table)->orderBy('scope')->orderBy('key')->get(['scope', 'key']) as $row) {
                if (! array_key_exists($row->key, (array) config("permissions.$table.{$row->scope}"))) {
                    $extra[] = "{$row->scope}.$label.{$row->key}";
                }
            }
        }

        return $extra;
    }

    /** @return list<string> */
    private static function run(bool $write): array
    {
        $added = [];

        foreach ((array) config('permissions.functions') as $scope => $functions) {
            foreach ($functions as $key => $function) {
                $functionId = DB::table('functions')->where(['scope' => $scope, 'key' => $key])->value('id');

                if ($functionId === null) {
                    $added[] = "$scope.function.$key (".implode(', ', $function['rights']).')';

                    if ($write) {
                        $functionId = DB::table('functions')->insertGetId([
                            'scope' => $scope,
                            'key' => $key,
                            'names' => json_encode($function['names'], JSON_UNESCAPED_UNICODE),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        foreach ($function['rights'] as $right) {
                            DB::table('function_rights')->insert(['function_id' => $functionId, 'right' => $right]);
                            self::record(self::FUNCTION_RIGHT, $scope, $key, $right);
                        }
                    }

                    continue;
                }

                foreach ($function['rights'] as $right) {
                    if (self::offered(self::FUNCTION_RIGHT, $scope, $key, $right)) {
                        continue;
                    }

                    if (! DB::table('function_rights')->where(['function_id' => $functionId, 'right' => $right])->exists()) {
                        $added[] = "$scope.function.$key + $right";

                        if ($write) {
                            DB::table('function_rights')->insert(['function_id' => $functionId, 'right' => $right]);
                        }
                    }

                    if ($write) {
                        self::record(self::FUNCTION_RIGHT, $scope, $key, $right);
                    }
                }
            }
        }

        foreach ((array) config('permissions.roles') as $scope => $roles) {
            foreach ($roles as $key => $role) {
                $roleId = DB::table('roles')->where(['scope' => $scope, 'key' => $key])->value('id');

                if ($roleId === null) {
                    $added[] = "$scope.role.$key (".implode(', ', $role['functions']).')';

                    if ($write) {
                        $roleId = DB::table('roles')->insertGetId([
                            'scope' => $scope,
                            'key' => $key,
                            'names' => json_encode($role['names'], JSON_UNESCAPED_UNICODE),
                            'project_cap' => ($role['project_cap'] ?? null) === null ? null : json_encode($role['project_cap']),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        foreach ($role['functions'] as $functionKey) {
                            self::attach($roleId, $scope, $functionKey);
                            self::record(self::ROLE_FUNCTION, $scope, $key, $functionKey);
                        }
                    }

                    continue;
                }

                foreach ($role['functions'] as $functionKey) {
                    if (self::offered(self::ROLE_FUNCTION, $scope, $key, $functionKey)) {
                        continue;
                    }

                    $functionId = DB::table('functions')->where(['scope' => $scope, 'key' => $functionKey])->value('id');
                    $attached = $functionId !== null && DB::table('role_functions')->where(['role_id' => $roleId, 'function_id' => $functionId])->exists();

                    if (! $attached) {
                        $added[] = "$scope.role.$key + function $functionKey";

                        if ($write) {
                            self::attach($roleId, $scope, $functionKey);
                        }
                    }

                    if ($write) {
                        self::record(self::ROLE_FUNCTION, $scope, $key, $functionKey);
                    }
                }
            }
        }

        return $added;
    }

    private static function attach(int $roleId, string $scope, string $functionKey): void
    {
        $functionId = DB::table('functions')->where(['scope' => $scope, 'key' => $functionKey])->value('id');

        if ($functionId !== null) {
            DB::table('role_functions')->insertOrIgnore(['role_id' => $roleId, 'function_id' => $functionId]);
        }
    }

    private static function offered(string $kind, string $scope, string $owner, string $item): bool
    {
        return DB::table('rights_defaults_applied')->where(['kind' => $kind, 'scope' => $scope, 'owner_key' => $owner, 'item' => $item])->exists();
    }

    private static function record(string $kind, string $scope, string $owner, string $item): void
    {
        DB::table('rights_defaults_applied')->insertOrIgnore([
            'kind' => $kind,
            'scope' => $scope,
            'owner_key' => $owner,
            'item' => $item,
            'applied_at' => now(),
        ]);
    }
}
