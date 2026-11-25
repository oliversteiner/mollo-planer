<?php

namespace Tests\Feature\GraphQL;

use App\Enums\ItemStatus;
use App\GraphQL\Errors\BillingRefusal;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Services\Billing\InvoicePdf;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Billing\InvoiceSheet;
use App\Support\Billing\CreditorReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Billing, part C (Auftrag 39): the PDF with the Swiss QR bill, kept when issued, never rendered again.
 */
class BillingPdfTest extends GraphQLTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->customer = Customer::factory()->create(['company' => 'Muster AG', 'name' => 'Hans Muster', 'street' => 'Bahnhofstrasse', 'house_number' => '1', 'postal_code' => '8001', 'city' => 'Zürich']);
        $this->project->forceFill(['customer_id' => $this->customer->id])->save();
        BillingSetting::current()->fill([
            'name' => 'Mollo', 'street' => 'Weg', 'house_number' => '2', 'postal_code' => '3000', 'city' => 'Bern',
            'country' => 'CH', 'iban' => 'CH9300762011623852957',
        ])->save();
    }

    private function draft(int $price = 150050): string
    {
        $milestone = Milestone::factory()->for($this->project)->create(['title' => 'Version 1.0']);
        $milestone->forceFill(['status' => ItemStatus::DONE, 'price_cents' => $price])->saveQuietly();

        return $this->as('admin')->gql('mutation ($c: ID!, $m: [ID!]!) { createInvoiceDraft(customerId: $c, milestoneIds: $m) { id } }', ['c' => $this->customer->id, 'm' => [$milestone->id]])
            ->assertJsonMissingPath('errors')
            ->json('data.createInvoiceDraft.id');
    }

    private function export(string $id): TestResponse
    {
        return $this->as('admin')->gql('mutation ($id: ID!) { exportInvoicePdf(id: $id) { fileName mimeType contentBase64 } }', ['id' => $id]);
    }

    public function test_issuing_keeps_the_pdf_and_the_export_returns_it_unchanged(): void
    {
        $id = $this->draft();
        $this->as('admin')->gql('mutation ($id: ID!) { issueInvoice(id: $id, issueDate: "2026-11-25") { hasPdf } }', ['id' => $id])
            ->assertJsonPath('data.issueInvoice.hasPdf', true);

        $invoice = Invoice::query()->find($id);
        $this->assertSame('invoices/2026/2026-0001.pdf', $invoice->pdf_path);
        $stored = Storage::disk('local')->get($invoice->pdf_path);
        $this->assertStringStartsWith('%PDF-', $stored);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $stored));

        // A change of the sender afterwards does not render it again.
        BillingSetting::current()->forceFill(['name' => 'Anders'])->save();
        $file = $this->export($id)->assertJsonMissingPath('errors')->json('data.exportInvoicePdf');

        $this->assertSame('rechnung-2026-0001.pdf', $file['fileName']);
        $this->assertSame('application/pdf', $file['mimeType']);
        $this->assertSame($stored, base64_decode($file['contentBase64']));
    }

    public function test_the_qr_code_holds_iban_amount_reference_and_both_addresses(): void
    {
        $id = $this->draft(150050);
        $this->as('admin')->gql('mutation ($id: ID!) { issueInvoice(id: $id, issueDate: "2026-11-25") { id } }', ['id' => $id]);

        $sheet = InvoiceSheet::fromInvoice(Invoice::query()->find($id));
        $text = app(InvoicePdf::class)->qrBill($sheet)->getQrCode()->getText();
        $lines = explode("\n", $text);

        $this->assertSame('SPC', $lines[0]);
        $this->assertContains('CH9300762011623852957', $lines);
        $this->assertContains('1500.50', $lines);
        $this->assertContains('CHF', $lines);
        $this->assertContains('SCOR', $lines);
        $this->assertContains(CreditorReference::fromNumber('2026-0001'), $lines);
        $this->assertTrue(CreditorReference::isValid($sheet->reference));
        $this->assertContains('Mollo', $lines);
        $this->assertContains('Muster AG', $lines);
        $this->assertContains('Bahnhofstrasse', $lines);
        $this->assertContains('Rechnung 2026-0001', $lines);
    }

    public function test_a_draft_has_a_preview_but_no_export(): void
    {
        $id = $this->draft();

        $file = $this->as('admin')->gql('mutation ($id: ID!) { previewInvoicePdf(id: $id) { fileName contentBase64 } }', ['id' => $id])
            ->assertJsonMissingPath('errors')->json('data.previewInvoicePdf');
        $this->assertSame('rechnung-entwurf-'.$id.'.pdf', $file['fileName']);
        $this->assertStringStartsWith('%PDF-', base64_decode($file['contentBase64']));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull(Invoice::query()->find($id)->number);

        $this->export($id)->assertJsonPath('errors.0.extensions.code', BillingRefusal::WRONG_STATUS);

        BillingSetting::current()->forceFill(['iban' => null])->save();
        $this->as('admin')->gql('mutation ($id: ID!) { previewInvoicePdf(id: $id) { fileName } }', ['id' => $id])
            ->assertJsonPath('errors.0.extensions.code', BillingRefusal::SENDER_INCOMPLETE)
            ->assertJsonPath('errors.0.extensions.missingFields', ['iban']);
    }

    public function test_a_pdf_that_fails_leaves_no_number_and_no_file(): void
    {
        $id = $this->draft();
        $this->app->instance(InvoicePdfRenderer::class, new class implements InvoicePdfRenderer
        {
            public function render(Invoice $invoice): string
            {
                throw new RuntimeException('render failed');
            }
        });

        $this->as('admin')->gql('mutation ($id: ID!) { issueInvoice(id: $id) { id } }', ['id' => $id])->assertJsonPath('errors.0.message', 'Internal server error');

        $this->assertNull(Invoice::query()->find($id)->number);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, (int) DB::table('invoice_number_sequences')->sum('last_number'));
    }

    public function test_an_english_customer_gets_an_english_invoice(): void
    {
        $this->customer->forceFill(['locale' => 'en'])->save();
        $id = $this->draft();
        $this->as('admin')->gql('mutation ($id: ID!) { issueInvoice(id: $id, issueDate: "2026-11-25") { locale } }', ['id' => $id])
            ->assertJsonPath('data.issueInvoice.locale', 'EN');

        $this->assertSame('invoice-2026-0001.pdf', $this->export($id)->json('data.exportInvoicePdf.fileName'));
        $this->assertContains('Invoice 2026-0001', explode("\n", app(InvoicePdf::class)->qrBill(InvoiceSheet::fromInvoice(Invoice::query()->find($id)), 'en')->getQrCode()->getText()));
    }

    public function test_money_is_printed_without_floats(): void
    {
        $this->assertSame('1’500.50', InvoicePdf::money(150050));
        $this->assertSame('9’999’999.99', InvoicePdf::money(999999999));
        $this->assertSame('0.05', InvoicePdf::money(5));
        $this->assertSame('1,500.00', InvoicePdf::money(150000, 'en'));
    }

    public function test_thirty_items_of_three_projects_stay_well_within_the_limits_of_the_server(): void
    {
        $start = microtime(true);
        $pdf = app(InvoicePdf::class)->renderSheet(InvoiceSheet::sample('de', 30, 3));
        $seconds = microtime(true) - $start;

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertLessThan(10, $seconds, 'The web host allows 60 s.');
        $this->assertLessThan(200 * 1024 * 1024, memory_get_peak_usage(true), 'The web host allows 256 MB.');
    }
}
