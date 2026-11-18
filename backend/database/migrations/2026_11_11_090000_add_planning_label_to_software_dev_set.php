<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Added the built-in label "Planung" to the set "Softwareentwicklung".
 * The built-in sets are templates now (2026_11_17_120000, planer:sync-templates),
 * so there is nothing left to do here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Moved to the templates: spec/templates/software-development.planer.json.
    }

    public function down(): void
    {
        // Labels are kept: tasks may already carry them.
    }
};
