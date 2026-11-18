<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ToDos get an order inside their milestone (schema-review A3). Existing
     * todos keep the order they were created in, numbered from 0 per milestone.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('milestone_id');
            $table->index(['milestone_id', 'position']);
        });

        $milestones = DB::table('todos')->distinct()->pluck('milestone_id');

        foreach ($milestones as $milestoneId) {
            $ids = DB::table('todos')->where('milestone_id', $milestoneId)->orderBy('id')->pluck('id');

            foreach ($ids as $position => $id) {
                DB::table('todos')->where('id', $id)->update(['position' => $position]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            // The foreign key on milestone_id needs an index of its own before the composite one goes.
            $table->index('milestone_id');
            $table->dropIndex(['milestone_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
