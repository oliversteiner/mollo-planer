<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release of a milestone (version and status), commit of a task, and the
 * revision counter of a project (docs/planer-abgleich.md §1, S3b; the counter
 * is counted in S3c). The rules are in App\Services\ReleaseRules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table): void {
            $table->string('release_version', 20)->nullable()->after('icon');
            $table->string('release_status', 20)->nullable()->after('release_version');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('commit', 40)->nullable()->after('status');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('planer_revision')->default(0)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('planer_revision');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('commit');
        });

        Schema::table('milestones', function (Blueprint $table): void {
            $table->dropColumn(['release_version', 'release_status']);
        });
    }
};
