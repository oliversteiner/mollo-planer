<?php

namespace App\Services\Billing;

use App\Models\Invoice;

/**
 * Renders the PDF of an issued invoice (Auftrag 39 C). The service keeps it; it is never rendered again.
 */
interface InvoicePdfRenderer
{
    /** The PDF as bytes. */
    public function render(Invoice $invoice): string;
}
