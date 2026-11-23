<?php

use App\Support\Rights;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The rights of the documents (docs/dokumentenverwaltung.md §4.1) reach the standard functions of an existing
 * installation: "Mitarbeiten" gets document.create and document.update, "Projekt verwalten" document.manageAny.
 * Only missing rows are added. Unlike permissions:sync-defaults this does not reset what was changed in the
 * settings (a function that was deleted gets nothing).
 */
return new class extends Migration
{
    private const GRANTS = [
        'contribute' => ['document.create', 'document.update'],
        'manage-project' => ['document.manageAny'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $functionKey => $rights) {
            $functionId = DB::table('functions')->where(['scope' => 'project', 'key' => $functionKey])->value('id');

            if ($functionId === null) {
                continue;
            }

            foreach ($rights as $right) {
                if (! DB::table('function_rights')->where(['function_id' => $functionId, 'right' => $right])->exists()) {
                    DB::table('function_rights')->insert(['function_id' => $functionId, 'right' => $right]);
                }
            }
        }

        Rights::clearCache();
    }

    public function down(): void
    {
        foreach (self::GRANTS as $functionKey => $rights) {
            $functionId = DB::table('functions')->where(['scope' => 'project', 'key' => $functionKey])->value('id');

            if ($functionId !== null) {
                DB::table('function_rights')->where('function_id', $functionId)->whereIn('right', $rights)->delete();
            }
        }

        Rights::clearCache();
    }
};
