<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The date a version was released (release.date of the planer file, task 29). Nullable and always optional: a
 * released milestone without a date stays valid (the validator hints at it), and no migration invents a date. Only
 * a released milestone has one; the rules are in App\Services\ReleaseRules and the MilestoneObserver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table): void {
            $table->date('release_date')->nullable()->after('release_status');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table): void {
            $table->dropColumn('release_date');
        });
    }
};
