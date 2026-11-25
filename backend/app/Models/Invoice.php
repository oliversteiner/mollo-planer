<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\RecordsCreator;
use App\Policies\BillingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An invoice of the billing (Auftrag 39 B). Changed only by App\Services\Billing\InvoiceService: a draft may change,
 * an issued invoice is frozen (number, addresses, items, PDF) and only its status moves on (paid, cancelled).
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $number
 * @property InvoiceStatus $status
 * @property Carbon|null $issue_date
 * @property Carbon|null $due_date
 * @property string $currency
 * @property int $total_cents
 * @property array<string, string|null>|null $recipient
 * @property array<string, string|null>|null $sender
 * @property string|null $reference
 * @property string $locale
 * @property string|null $pdf_path
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $issued_by
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
#[UsePolicy(BillingPolicy::class)]
class Invoice extends Model
{
    use RecordsCreator;

    protected $attributes = ['status' => 'draft', 'currency' => 'CHF', 'total_cents' => 0, 'locale' => 'de'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'total_cents' => 'integer',
            'recipient' => 'array',
            'sender' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::DRAFT;
    }

    /** Issued and the due date has passed (not a status of its own). */
    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->status === InvoiceStatus::ISSUED
            && $this->due_date !== null
            && $this->due_date->lt(($today ?? Carbon::today())->startOfDay());
    }
}
