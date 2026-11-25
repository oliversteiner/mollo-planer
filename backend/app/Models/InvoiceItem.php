<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of an invoice: one milestone at its fixed price (Auftrag 39 B). project_name, title, description and
 * amount are copies; project and milestone may be deleted later without changing the invoice.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $project_id
 * @property string $project_name
 * @property int|null $milestone_id
 * @property int|null $billing_milestone_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property int $amount_cents
 */
class InvoiceItem extends Model
{
    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /** @return BelongsTo<Milestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class)->withTrashed();
    }
}
