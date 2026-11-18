<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the text of a comment was last changed (docs/fix-m2.md F5.6). Only
 * changing the text sets it; deleting and restoring touch updated_at, which
 * made restored comments look edited. Existing comments changed after their
 * creation count as edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable();
        });

        DB::table('comments')
            ->whereNull('deleted_at')
            ->whereColumn('updated_at', '>', 'created_at')
            ->update(['edited_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
