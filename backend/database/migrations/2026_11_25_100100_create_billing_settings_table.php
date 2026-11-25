<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (Auftrag 39 A): the sender of the invoices, one row. Structured address (QR bill), the IBAN the money goes to,
 * the terms and the format of the invoice number. The letterhead and its layout follow in Auftrag 39 F.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 70)->nullable();
            $table->string('street', 70)->nullable();
            $table->string('house_number', 16)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 35)->nullable();
            $table->char('country', 2)->default('CH');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('uid', 20)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->string('number_format', 40)->default('{YYYY}-{NNNN}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
