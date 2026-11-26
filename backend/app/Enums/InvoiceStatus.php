<?php

namespace App\Enums;

/**
 * Where an invoice stands (Auftrag 39 B). Overdue is not a status: it is derived (issued, due date passed).
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    /** The invoice holds its milestones: no other invoice may take them. */
    public function holdsMilestones(): bool
    {
        return $this !== self::CANCELLED;
    }
}
