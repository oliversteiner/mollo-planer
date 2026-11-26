<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per tool call of an AI client (Auftrag 43 C): which tool, whose account, how many characters the answer had,
     * how long it took. Never the arguments or the answer. The command app:mcp-usage adds it up.
     */
    public function up(): void
    {
        Schema::create('mcp_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('tool', 64);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedInteger('chars');
            $table->unsignedInteger('duration_ms');
            $table->boolean('ok');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
    }
};
