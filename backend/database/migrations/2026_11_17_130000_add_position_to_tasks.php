<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The place of a task in its todo (docs/auftrag-m4.md §2.3), apart from
 * board_position, its place in the Kanban column. The planer file lists the
 * tasks of a todo in this order. Start values follow what the timeline has
 * shown until now: due date (without a date last), then id. Tasks in the
 * trash get their place when they come back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('board_position');
            $table->index(['todo_id', 'position']);
        });

        $position = [];

        DB::table('tasks')
            ->whereNull('deleted_at')
            ->orderBy('todo_id')
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->select(['id', 'todo_id'])
            ->each(function (object $task) use (&$position): void {
                $position[$task->todo_id] = ($position[$task->todo_id] ?? -1) + 1;
                DB::table('tasks')->where('id', $task->id)->update(['position' => $position[$task->todo_id]]);
            });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['todo_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
