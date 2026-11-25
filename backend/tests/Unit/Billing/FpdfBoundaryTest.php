<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * FPDF is the second PDF library, only for the payment part of the QR bill (Auftrag 39, decision Oliver 25.11.):
 * sprain/swiss-qr-bill draws it with FPDF, mPDF makes every other page and places that one page with FPDI.
 * Nothing else may use FPDF (docs/abrechnung.md).
 */
class FpdfBoundaryTest extends TestCase
{
    private const ALLOWED = [
        'app/Services/Billing/PaymentPartPage.php',
        'app/Services/Billing/InvoicePdf.php',
    ];

    public function test_only_the_payment_part_uses_fpdf(): void
    {
        $root = dirname(__DIR__, 3);
        $users = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            if (preg_match('/\\\\?\bFpdf\\\\|\bnew\s+\\\\?FPDF\b|extends\s+\\\\?FPDF\b/', $code) === 1) {
                $users[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($users);

        $this->assertSame([], array_values(array_diff($users, self::ALLOWED)), 'FPDF is only for the payment part (docs/abrechnung.md).');
        $this->assertContains('app/Services/Billing/PaymentPartPage.php', $users, 'the check must see the one place that uses FPDF');
    }
}
