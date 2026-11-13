<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created a milestone, todo or task (schema-review A2). Set from the
     * login by App\Models\Concerns\RecordsCreator, never from input.
     */
    public function up(): void
    {
        foreach (['milestones', 'todos', 'tasks'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['milestones', 'todos', 'tasks'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
