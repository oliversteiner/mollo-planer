<?php

namespace App\GraphQL\Errors;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

/**
 * A billing step that cannot be done (Auftrag 39 B). The error carries a code the frontend can act on
 * (extensions.code) and the details (for example the milestones or the missing fields); the message is the sentence in
 * the language of the request. Nothing was changed.
 */
final class BillingRefusal extends RuntimeException implements ClientAware, ProvidesExtensions
{
    /** A milestone is not done, has no price, is in the trash or already in another invoice. */
    public const NOT_BILLABLE = 'MILESTONE_NOT_BILLABLE';

    /** The milestones belong to projects of another customer than the invoice. */
    public const OTHER_CUSTOMER = 'OTHER_CUSTOMER';

    /** The project of a milestone has no customer. */
    public const CUSTOMER_MISSING = 'CUSTOMER_MISSING';

    /** Required fields of the sender are empty (details.missingFields). */
    public const SENDER_INCOMPLETE = 'SENDER_INCOMPLETE';

    /** The step needs another status (for example: only a draft can be changed). */
    public const WRONG_STATUS = 'INVOICE_WRONG_STATUS';

    /** An invoice needs at least one item. */
    public const EMPTY = 'INVOICE_EMPTY';

    /** The number made from the format does not fit a creditor reference (more than 21 letters or digits). */
    public const NUMBER_TOO_LONG = 'INVOICE_NUMBER_TOO_LONG';

    /** The total is above the limit of the QR bill (CHF 9 999 999.99). */
    public const TOTAL_TOO_HIGH = 'INVOICE_TOTAL_TOO_HIGH';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public readonly string $reason, public readonly array $details = [])
    {
        parent::__construct(__('billing.refusal.'.$reason, self::replacements($details)));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'billing';
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        // refusal: the message is written for people (the frontend shows it as it is).
        return ['code' => $this->reason, 'refusal' => true, ...$this->details];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    private static function replacements(array $details): array
    {
        return array_map(fn (mixed $value): string => is_array($value) ? implode(', ', $value) : (string) $value, array_filter($details, fn (mixed $value): bool => is_scalar($value) || is_array($value)));
    }
}
