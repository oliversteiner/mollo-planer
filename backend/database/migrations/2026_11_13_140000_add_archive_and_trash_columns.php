<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns only (schema-review A5): the archive of projects and the trash of
     * projects, milestones, todos, tasks and comments. The logic follows later;
     * deletion still removes rows for now.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['deletion_id']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deletion_id', 'deleted_at']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['deletion_id']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deletion_id', 'deleted_at']);
        });
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['deletion_id']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deletion_id', 'deleted_at']);
        });
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex(['deletion_id']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deletion_id', 'deleted_at']);
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['deletion_id']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deletion_id', 'deleted_at', 'archived_at']);
        });
    }
};
