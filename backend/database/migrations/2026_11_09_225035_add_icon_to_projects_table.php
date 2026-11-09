<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Icon of a project (shown in the sidebar), like milestones, todos and tasks.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('description'); // Font Awesome icon name, style is fixed in the frontend
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
