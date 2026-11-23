<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which admin registered the client. Empty: the application registered itself (dynamic client registration), nobody at Mollo
     * has looked at it; the consent page says so (acceptance of Auftrag 41, F5).
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->unsignedBigInteger('registered_by')->nullable()->after('revoked');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('registered_by');
        });
    }
};
