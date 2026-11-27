<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * FPDF (and FPDI, which builds on it) only where it belongs (Auftrag 39, decision Oliver 25.11.; widened 27.11. with
 * Auftrag 81 B): sprain/swiss-qr-bill draws the payment part of the QR bill with FPDF, and the PDF frame of the base
 * (App\Core\Pdf) puts pages together with FPDI (letterhead, letter pages of dompdf, payment part, watermark).
 * No module code uses them (docs/abrechnung.md).
 */
class FpdfBoundaryTest extends TestCase
{
    private const ALLOWED = [
        'app/Services/Billing/PaymentPartPage.php',
        'app/Services/Billing/InvoicePdf.php',
    ];

    /** The PDF frame of the base may use FPDI and FPDF everywhere. */
    private const ALLOWED_DIRECTORY = 'app/Core/Pdf/';

    public function test_only_the_payment_part_uses_fpdf(): void
    {
        $root = dirname(__DIR__, 3);
        $users = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            if (preg_match('/\\\\?\bFpdf\\\\|\bnew\s+\\\\?FPDF\b|extends\s+\\\\?FPDF\b|setasign\\\\Fpdi\\\\(Fpdi|Tcpdf|FpdfTpl)\b/', $code) === 1) {
                $users[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($users);

        $outside = array_filter($users, fn (string $path): bool => ! str_starts_with($path, self::ALLOWED_DIRECTORY));

        $this->assertSame([], array_values(array_diff($outside, self::ALLOWED)), 'FPDF and FPDI only for the payment part and the PDF frame (docs/abrechnung.md).');
        $this->assertContains('app/Core/Pdf/PdfFrame.php', $users, 'the check must see FPDI in the PDF frame');
        $this->assertContains('app/Services/Billing/PaymentPartPage.php', $users, 'the check must see the one place that uses FPDF');
    }
}
