<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents of a project (docs/dokumentenverwaltung.md §3): Markdown in the canonical form of the profile
 * "Dokument" (the only source), a share level for all project members plus shares per person, and an
 * unchangeable version for every save. In the trash like the plan items (deleted_at, deleted_by, deletion_id);
 * a deleted project takes its documents along with the same deletion_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('kind', 32)->default('other'); // procedure, minutes, analysis, concept, other
            $table->longText('content'); // Markdown, canonical form, at most 200 000 characters
            $table->string('project_access', 8)->default('read'); // none, read, edit: for all project members
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('deletion_id')->nullable()->index();

            $table->index(['project_id', 'deleted_at', 'updated_at']);
            $table->index(['project_id', 'kind']);
        });

        // The full-text search exists on MariaDB only; the tests on SQLite search with LIKE.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->fullText(['title', 'content']);
            });
        }

        Schema::create('document_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access', 8); // read, edit

            $table->unique(['document_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title', 200);
            $table->string('kind', 32);
            $table->longText('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('contributors')->nullable(); // M8: everybody who changed something since the version before
            $table->unsignedInteger('restored_from')->nullable(); // the revision it was made from by restoring
            $table->timestamp('created_at')->nullable();

            $table->unique(['document_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('documents');
    }
};
