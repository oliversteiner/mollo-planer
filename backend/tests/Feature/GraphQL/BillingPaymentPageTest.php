<?php

namespace Tests\Feature\GraphQL;

use App\Services\Billing\InvoicePdf;
use App\Services\Billing\InvoiceSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mpdf\Mpdf;
use ReflectionMethod;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

/**
 * The page FPDF makes for the payment part can be read by FPDI (Auftrag 39, decision Oliver 25.11.): mPDF places it
 * with FPDI, and the free FPDI cannot read compressed object streams. An update of FPDF that changed this would
 * otherwise break the invoices unnoticed.
 */
class BillingPaymentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fpdi_reads_the_payment_page_of_fpdf_as_one_page_of_a4(): void
    {
        $page = (new ReflectionMethod(InvoicePdf::class, 'paymentPage'))->invoke(app(InvoicePdf::class), InvoiceSheet::sample('de', 3, 2), 'de');

        $mpdf = new Mpdf(['tempDir' => storage_path('framework/cache/mpdf')]);
        $this->assertSame(1, $mpdf->setSourceFile(StreamReader::createByString($page)));

        $size = $mpdf->getTemplateSize($mpdf->importPage(1));
        $this->assertEqualsWithDelta(210, $size['width'], 0.5);
        $this->assertEqualsWithDelta(297, $size['height'], 0.5);
    }
}
