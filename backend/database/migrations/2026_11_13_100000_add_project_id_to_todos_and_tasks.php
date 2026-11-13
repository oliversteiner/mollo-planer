<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ToDos and tasks know their project directly (schema-review A1). The value
     * comes from the parent, see App\Models\Concerns\DerivesProject, and is
     * filled in for existing rows from the parent here.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('milestone_id')->constrained()->cascadeOnDelete();
        });

        DB::statement('UPDATE todos SET project_id = (SELECT milestones.project_id FROM milestones WHERE milestones.id = todos.milestone_id)');

        Schema::table('todos', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('todo_id')->constrained()->cascadeOnDelete();
            $table->index(['project_id', 'status', 'position']);
        });

        DB::statement('UPDATE tasks SET project_id = (SELECT todos.project_id FROM todos WHERE todos.id = tasks.todo_id)');

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // MariaDB needs an index that starts with project_id for the foreign key,
            // and the composite index below is the only one there is.
            $table->index('project_id');
            $table->dropIndex(['project_id', 'status', 'position']);
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
