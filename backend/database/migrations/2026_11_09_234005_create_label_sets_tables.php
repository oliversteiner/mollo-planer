<?php

use App\Support\BuiltinLabelSets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Label sets (app-wide) with their labels, the set a project uses and
     * the labels of a task. The built-in sets are created here, so they
     * exist on every installation.
     */
    public function up(): void
    {
        Schema::create('label_sets', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->nullable()->unique(); // only built-in sets, translations are keyed by it
            $table->string('name', 60);
            $table->string('description', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_set_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60)->nullable(); // only labels of built-in sets
            $table->string('name', 40);
            $table->string('category', 40)->nullable();
            $table->string('color', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['label_set_id', 'name']);
            $table->unique(['label_set_id', 'key']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // A set in use cannot be deleted.
            $table->foreignId('label_set_id')->nullable()->after('icon')->constrained()->restrictOnDelete();
        });

        Schema::create('label_task', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->primary(['label_id', 'task_id']);
            $table->index('task_id');
        });

        BuiltinLabelSets::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('label_task');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_set_id');
        });
        Schema::dropIfExists('labels');
        Schema::dropIfExists('label_sets');
    }
};
