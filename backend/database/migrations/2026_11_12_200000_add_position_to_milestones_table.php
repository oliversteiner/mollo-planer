<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Milestones get an order inside their project. Existing milestones keep
     * the order they were created in (by id), numbered from 0.
     */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('project_id');
            $table->index(['project_id', 'position']);
        });

        $projects = DB::table('milestones')->distinct()->pluck('project_id');

        foreach ($projects as $projectId) {
            $ids = DB::table('milestones')->where('project_id', $projectId)->orderBy('id')->pluck('id');

            foreach ($ids as $position => $id) {
                DB::table('milestones')->where('id', $id)->update(['position' => $position]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            // MariaDB needs an index that starts with project_id for the foreign key,
            // and the composite index below is the only one there is.
            $table->index('project_id');
            $table->dropIndex(['project_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
