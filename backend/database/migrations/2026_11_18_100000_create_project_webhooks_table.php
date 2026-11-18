<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One webhook per project (docs/auftrag-m5.md §4.1): the app tells GitHub
 * (repository_dispatch) or any URL (signed) that the planer file has a new
 * revision. The secret is stored encrypted and never shown again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // github, generic
            $table->string('target', 500); // github: owner/name, generic: URL
            $table->text('secret'); // encrypted: GitHub token or HMAC secret
            $table->unsignedInteger('last_sent_revision')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error', 100)->nullable(); // a short code or "HTTP 401", never headers or body
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_webhooks');
    }
};
