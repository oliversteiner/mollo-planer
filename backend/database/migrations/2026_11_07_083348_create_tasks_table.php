<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->json('description')->nullable(); // TipTap document, never HTML
            $table->string('icon', 60)->nullable(); // Font Awesome icon name, style is fixed in the frontend
            $table->string('status', 20)->default('open'); // open, in_progress, review, done
            $table->unsignedInteger('position')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['todo_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
