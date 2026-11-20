<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a task was done (task 33, the course in the statistics). Set by the TaskObserver when the status becomes
 * done, cleared when it leaves done. Tasks that were done before this column have no time and stay without one.
 * Not part of the planer file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('done_at')->nullable()->after('status');
            $table->index(['project_id', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'done_at']);
            $table->dropColumn('done_at');
        });
    }
};
