<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * People and memberships point to a row of roles instead of carrying a role
 * text (docs/rollen-funktionen-rechte.md, §8). This makes roles of one's own
 * possible. A missing role_id gets the default role of its scope from the
 * app (roles.is_default).
 */
return new class extends Migration
{
    private const DEFAULTS = ['system' => 'mitglied', 'project' => 'member'];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('project_cap');
        });

        foreach (self::DEFAULTS as $scope => $key) {
            DB::table('roles')->where(['scope' => $scope, 'key' => $key])->update(['is_default' => true]);
        }

        // One block per table, with the table name written out, so that the
        // schema documentation (frontend tests) can read the columns.
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
        });
        Schema::table('project_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
        });

        $this->fillRoleIds('users', 'system');
        $this->fillRoleIds('project_user', 'project');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
        Schema::table('project_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /** Points every row to the role of its old role text; without one, to the default role of its scope. */
    private function fillRoleIds(string $tableName, string $scope): void
    {
        $fallback = DB::table('roles')->where(['scope' => $scope, 'is_default' => true])->value('id');

        foreach (DB::table('roles')->where('scope', $scope)->pluck('id', 'key') as $key => $id) {
            DB::table($tableName)->where('role', $key)->update(['role_id' => $id]);
        }

        DB::table($tableName)->whereNull('role_id')->update(['role_id' => $fallback]);
    }

    public function down(): void
    {
        foreach (['users' => 'system', 'project_user' => 'project'] as $tableName => $scope) {
            Schema::table($tableName, function (Blueprint $table) use ($scope): void {
                $table->string('role', 20)->default(self::DEFAULTS[$scope]);
            });

            foreach (DB::table('roles')->where('scope', $scope)->pluck('key', 'id') as $id => $key) {
                DB::table($tableName)->where('role_id', $id)->update(['role' => $key]);
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('role_id');
            });
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('is_default');
        });
    }
};
