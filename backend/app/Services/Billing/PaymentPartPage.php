<?php

namespace App\Services\Billing;

use Fpdf\Fpdf;
use Fpdf\Traits\MemoryImageSupport\MemImageTrait;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfTrait;

/**
 * The FPDF page the payment part is drawn on (Auftrag 39 C): with the trait of sprain/swiss-qr-bill (scissors, dashed
 * lines) and images from memory (the QR code, no allow_url_fopen needed).
 */
final class PaymentPartPage extends Fpdf
{
    use FpdfTrait;
    use MemImageTrait;
}
