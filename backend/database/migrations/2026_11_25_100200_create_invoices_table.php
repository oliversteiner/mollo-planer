<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (Auftrag 39 B): invoices, their items and the number ranges.
 *
 * - The number is empty in a draft and given when the invoice is issued, from invoice_number_sequences (one row per
 *   year, or year 0 when the format has no year), taken with a row lock: without gaps and unique.
 * - invoice_items.billing_milestone_id is the milestone while the invoice holds it (draft, issued, paid) and null once it
 *   is cancelled. It is unique: a milestone is in at most one such invoice, also when two drafts are made at the same time.
 * - Issued invoices are never deleted; recipient, sender, project names and items are frozen copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('number', 40)->nullable()->unique();
            $table->string('status', 16)->default('draft')->index();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->char('currency', 3)->default('CHF');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->json('recipient')->nullable();
            $table->json('sender')->nullable();
            $table->string('reference', 25)->nullable();
            $table->string('locale', 5)->default('de');
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('project_name', 120);
            $table->foreignId('milestone_id')->nullable()->constrained('milestones')->nullOnDelete();
            $table->foreignId('billing_milestone_id')->nullable()->unique()->constrained('milestones')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
        });

        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
