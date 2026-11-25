<?php

namespace App\Services\Billing;

use App\Core\Pdf\PageLayout;
use App\Core\Pdf\PdfFrame;
use App\GraphQL\Errors\BillingRefusal;
use App\Models\Invoice;
use App\Support\Billing\CreditorReference;
use App\Support\Billing\Iban;
use Illuminate\Support\Facades\View;
use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\PaymentPart\Output\DisplayOptions;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfOutput;
use Sprain\SwissQrBill\QrBill;

/**
 * The PDF of an invoice (Auftrag 39 C): letter pages in the frame of the starter kit (PdfFrame, PageLayout), items
 * grouped by project, then the payment part of the Swiss QR bill on a page of its own at the end (bottom 105 mm,
 * receipt 62 mm, payment part 148 mm), without header, footer or letterhead. The payment part comes from
 * sprain/swiss-qr-bill (FPDF output, placed as a page with FPDI), with the creditor reference (SCOR). Texts in the
 * language of the invoice.
 */
final class InvoicePdf implements InvoicePdfRenderer
{
    public function __construct(private readonly PdfFrame $frame) {}

    public function render(Invoice $invoice): string
    {
        return $this->renderSheet(InvoiceSheet::fromInvoice($invoice));
    }

    /**
     * @throws BillingRefusal when the sender lacks what the QR bill needs
     */
    public function renderSheet(InvoiceSheet $sheet, ?PageLayout $layout = null): string
    {
        $layout ??= new PageLayout;
        $locale = in_array($sheet->locale, ['de', 'en'], true) ? $sheet->locale : 'de';
        $texts = fn (string $key, array $replace = []): string => (string) __('billing.pdf.'.$key, $replace, $locale);

        $mpdf = $this->frame->make([
            'top' => $layout->topFollowing,
            'bottom' => $layout->bottom,
            'left' => $layout->left,
            'right' => $layout->right,
            'header' => 10,
            'footer' => 10,
        ]);
        $mpdf->SetTitle($sheet->number !== null ? $texts('title_number', ['number' => $sheet->number]) : $texts('title'));

        if ($sheet->draft) {
            $mpdf->SetWatermarkText($texts('draft'), 0.08);
            $mpdf->showWatermarkText = true;
        }

        $mpdf->SetHTMLFooter(View::make('invoices.pdf-footer', ['page' => $texts('page', ['current' => '{PAGENO}', 'total' => '{nbpg}'])])->render());

        $mpdf->WriteHTML(View::make('invoices.pdf', [
            'sheet' => $sheet,
            'layout' => $layout,
            'locale' => $locale,
            'texts' => $texts,
            'money' => fn (int $cents): string => self::money($cents, $locale),
            'date' => fn (\DateTimeInterface $date): string => self::date($date, $locale),
            'iban' => Iban::format((string) ($sheet->sender['iban'] ?? '')),
            'reference' => $sheet->reference !== null ? CreditorReference::format($sheet->reference) : null,
        ])->render());

        // The payment part: a page of its own, without header, footer, margins or letterhead; the page made by the
        // library is placed on it unchanged.
        $mpdf->AddPageByArray([
            'margin-left' => 0, 'margin-right' => 0, 'margin-top' => 0, 'margin-bottom' => 0, 'margin-header' => 0, 'margin-footer' => 0,
            'odd-header-value' => -1, 'even-header-value' => -1, 'odd-footer-value' => -1, 'even-footer-value' => -1,
        ]);
        $mpdf->SetSourceFile(StreamReader::createByString($this->paymentPage($sheet, $locale)));
        $mpdf->useTemplate($mpdf->importPage(1), 0, 0, 210, 297);

        return $mpdf->OutputBinaryData();
    }

    /** A file name for the download. */
    public static function fileName(Invoice $invoice): string
    {
        $number = $invoice->number !== null ? preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice->number) : 'entwurf-'.$invoice->id;

        return ($invoice->locale === 'en' ? 'invoice-' : 'rechnung-').$number.'.pdf';
    }

    /** CHF amount as printed: 1’250.50 (de) or 1,250.50 (en). */
    public static function money(int $cents, string $locale = 'de'): string
    {
        $francs = intdiv($cents, 100);
        $rest = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return number_format($francs, 0, '.', $locale === 'en' ? ',' : '’').'.'.$rest;
    }

    private static function date(\DateTimeInterface $date, string $locale): string
    {
        return $date->format('d.m.Y');
    }

    /**
     * The payment part as a page of A4 (PDF bytes), made by sprain/swiss-qr-bill with FPDF: the elements at the
     * positions of the Swiss Payment Standards, the QR code with the Swiss cross. Checked by the library first.
     */
    private function paymentPage(InvoiceSheet $sheet, string $locale): string
    {
        $bill = $this->qrBill($sheet, $locale);

        $page = new PaymentPartPage('P', 'mm', 'A4');
        $page->SetAutoPageBreak(false);
        $page->AddPage();
        $output = new FpdfOutput($bill, $locale, $page);
        $output->setDisplayOptions((new DisplayOptions)->setPrintable(false)->setDisplayScissors(true));
        $output->getPaymentPart();

        return $page->Output('S');
    }

    /**
     * The data of the QR bill, checked by the library (Swiss Payment Standards).
     *
     * @throws RuntimeException when the data break a rule of the QR bill
     */
    public function qrBill(InvoiceSheet $sheet, string $locale = 'de'): QrBill
    {
        $sender = $sheet->sender;
        $recipient = $sheet->recipient;

        $bill = QrBill::create();
        $bill->setCreditor(StructuredAddress::createWithStreet(
            (string) $sender['name'], (string) $sender['street'], $sender['houseNumber'] ?? null, (string) $sender['postalCode'], (string) $sender['city'], (string) $sender['country'],
        ));
        $bill->setCreditorInformation(CreditorInformation::create(Iban::normalize((string) $sender['iban'])));
        $bill->setUltimateDebtor(StructuredAddress::createWithStreet(
            (string) (filled($recipient['company'] ?? null) ? $recipient['company'] : $recipient['name']), (string) $recipient['street'], $recipient['houseNumber'] ?? null, (string) $recipient['postalCode'], (string) $recipient['city'], (string) $recipient['country'],
        ));
        $bill->setPaymentAmountInformation(PaymentAmountInformation::create('CHF', $sheet->totalCents / 100));
        $bill->setPaymentReference($sheet->reference !== null
            ? PaymentReference::create(PaymentReference::TYPE_SCOR, $sheet->reference)
            : PaymentReference::create(PaymentReference::TYPE_NON));
        $bill->setAdditionalInformation(AdditionalInformation::create(
            $sheet->number !== null ? (string) __('billing.pdf.title_number', ['number' => $sheet->number], $locale) : (string) __('billing.pdf.title', [], $locale),
        ));

        if (! $bill->isValid()) {
            $fields = [];

            foreach ($bill->getViolations() as $violation) {
                $fields[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }

            throw new RuntimeException('QR bill is not valid: '.implode('; ', $fields));
        }

        return $bill;
    }
}
