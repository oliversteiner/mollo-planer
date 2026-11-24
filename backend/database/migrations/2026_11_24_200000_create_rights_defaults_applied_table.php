<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The standard pairs (a right in a function, a function in a role) that were already offered to this database (Auftrag 40 B).
 * A pair that was offered and is missing today was removed in the settings: permissions:sync-defaults --add-missing does not
 * add it again. Filled by App\Core\Rights\DefaultsAddMissing and by the reset; empty on an existing database until the first run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rights_defaults_applied', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('scope', 10);
            $table->string('owner_key', 64);
            $table->string('item', 64);
            $table->timestamp('applied_at')->nullable();
            $table->unique(['kind', 'scope', 'owner_key', 'item'], 'rights_defaults_applied_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rights_defaults_applied');
    }
};
