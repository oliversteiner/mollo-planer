<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI context of a project (docs/auftrag-m6.md §3.1): background knowledge for the assistant, as text and
 * as the extracted text of one uploaded file (the file itself is not kept). Everybody in the project reads it;
 * whoever may change the project changes it. It is no part of the planer file and never counts as a revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ai_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('text')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->longText('file_text')->nullable();
            $table->boolean('shared_with_tools')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ai_contexts');
    }
};
