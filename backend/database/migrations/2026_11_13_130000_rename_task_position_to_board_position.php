<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The position of a task is its place in the Kanban column (schema-review
     * A3): board_position. "position" then means "place in the parent" for
     * milestones, todos and tasks alike.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('position', 'board_position');
            $table->renameIndex('tasks_project_id_status_position_index', 'tasks_project_id_status_board_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->renameIndex('tasks_project_id_status_board_position_index', 'tasks_project_id_status_position_index');
            $table->renameColumn('board_position', 'position');
        });
    }
};
