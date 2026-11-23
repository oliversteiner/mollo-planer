<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the id_token needs and the authorization code does not carry: the nonce of the request and how and when
     * the person logged in. Written when the code is issued, read when the code is exchanged.
     */
    public function up(): void
    {
        Schema::create('oauth_auth_code_claims', function (Blueprint $table): void {
            $table->string('auth_code_id', 100)->primary();
            $table->string('nonce')->nullable();
            $table->json('amr');
            $table->unsignedBigInteger('auth_time');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_auth_code_claims');
    }
};
