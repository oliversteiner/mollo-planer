<?php

namespace App\Core\Pdf;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

/**
 * An uploaded letterhead that cannot be used (Auftrag 39 F). extensions.code says why; nothing was stored.
 */
final class LetterheadRejected extends RuntimeException implements ClientAware, ProvidesExtensions
{
    public const NOT_PDF = 'LETTERHEAD_NOT_PDF';

    public const TOO_LARGE = 'LETTERHEAD_TOO_LARGE';

    /** FPDI cannot read it (often compressed object streams, PDF 1.5 and later): export as PDF 1.4 or PDF/X-1a. */
    public const NOT_READABLE = 'LETTERHEAD_NOT_READABLE';

    public const NOT_A4 = 'LETTERHEAD_NOT_A4';

    public const PAGES = 'LETTERHEAD_PAGES';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(__('pdf.letterhead.'.$reason));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'pdf';
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtensions(): array
    {
        // refusal: the message is written for people (the frontend shows it as it is).
        return ['code' => $this->reason, 'refusal' => true];
    }
}
