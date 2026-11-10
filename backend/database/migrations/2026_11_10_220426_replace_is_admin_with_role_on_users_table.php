<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the global admin flag with a role. Everyone who was an admin
 * becomes "admin", everyone else "mitglied". The job function is display
 * only and optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('mitglied')->after('email');
            $table->string('job_function', 20)->nullable()->after('role');
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('email');
        });

        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'job_function']);
        });
    }
};
