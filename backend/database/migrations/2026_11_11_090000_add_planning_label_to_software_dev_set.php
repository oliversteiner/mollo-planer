<?php

use App\Support\BuiltinLabelSets;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the built-in label "Planung" to the set "Softwareentwicklung". The sync
 * matches sets and labels by key, so existing labels are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        BuiltinLabelSets::sync();
    }

    public function down(): void
    {
        // Labels are kept: tasks may already carry them.
    }
};
