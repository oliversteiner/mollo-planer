<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (Auftrag 39 A): the customers, the price of a milestone (whole Rappen, never a float), the customer of a
 * project. The address is structured (street, house number, postal code, town, country): the Swiss QR bill accepts only
 * that since version 2.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company', 70)->nullable();
            $table->string('name', 70);
            $table->string('street', 70);
            $table->string('house_number', 16)->nullable();
            $table->string('postal_code', 16);
            $table->string('city', 35);
            $table->char('country', 2)->default('CH');
            $table->string('email')->nullable();
            $table->string('locale', 5)->default('de');
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('locale')->constrained('customers')->nullOnDelete();
        });

        Schema::table('milestones', function (Blueprint $table) {
            $table->unsignedBigInteger('price_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('price_cents');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
