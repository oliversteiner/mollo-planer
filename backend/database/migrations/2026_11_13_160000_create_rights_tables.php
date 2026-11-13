<?php

use App\Support\RightsDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, functions and rights as data (docs/rollen-funktionen-rechte.md, §8).
 * The standard rows are written from config/permissions.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('functions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 10);
            $table->string('key', 64);
            $table->json('names');
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });

        Schema::create('function_rights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('function_id')->constrained('functions')->cascadeOnDelete();
            $table->string('right', 64);
            $table->unique(['function_id', 'right']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 10);
            $table->string('key', 64);
            $table->json('names');
            $table->json('project_cap')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'key']);
        });

        Schema::create('role_functions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('functions')->cascadeOnDelete();
            $table->primary(['role_id', 'function_id']);
            $table->index('function_id');
        });

        RightsDefaults::apply();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_functions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('function_rights');
        Schema::dropIfExists('functions');
    }
};
