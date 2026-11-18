<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ideas (docs/auftrag-m6.md §2.1): private to their author, optionally tied to a project, with an optional
 * draft (a planer-v2 excerpt) that the assistant can put into a project later. They are in the trash like
 * the plan items (deleted_at, deleted_by, deletion_id). A todo made from an idea knows it (todos.idea_id).
 * Ideas are no part of the planer file and never count as a revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->json('description')->nullable(); // TipTap document, never HTML
            $table->longText('draft')->nullable(); // canonical planer-v2 excerpt
            $table->string('status', 20)->default('open'); // open, adopted, discarded
            $table->timestamp('adopted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();

            $table->index(['user_id', 'status']);
        });

        Schema::table('todos', function (Blueprint $table): void {
            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('idea_id');
        });

        Schema::dropIfExists('ideas');
    }
};
