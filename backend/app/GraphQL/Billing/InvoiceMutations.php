<?php

namespace App\GraphQL\Billing;

use App\GraphQL\Concerns\ResolvesViewer;
use App\GraphQL\Errors\BillingRefusal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoiceService;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * The steps of an invoice (Auftrag 39 B), all in InvoiceService. billing.manage is checked by @can.
 */
class InvoiceMutations
{
    use ResolvesViewer;

    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * @param  array{customerId: string, milestoneIds: list<string>, notes?: string|null}  $args
     *
     * @throws BillingRefusal
     */
    public function createDraft(mixed $root, array $args, GraphQLContext $context): Invoice
    {
        return $this->invoices->createDraft($this->viewer($context), Customer::query()->findOrFail($args['customerId']), $args['milestoneIds'], $args['notes'] ?? null);
    }

    /**
     * @param  array{id: string, notes?: string|null, items?: list<array{id: string, description?: string|null}>|null}  $args
     *
     * @throws BillingRefusal
     */
    public function updateDraft(mixed $root, array $args): Invoice
    {
        return $this->invoices->updateDraft(Invoice::query()->findOrFail($args['id']), $args['notes'] ?? null, $args['items'] ?? null, array_key_exists('notes', $args));
    }

    /**
     * @param  array{id: string}  $args
     *
     * @throws BillingRefusal
     */
    public function deleteDraft(mixed $root, array $args): string
    {
        return (string) $this->invoices->deleteDraft(Invoice::query()->findOrFail($args['id']))->id;
    }

    /**
     * @param  array{id: string, issueDate?: Carbon|null}  $args
     *
     * @throws BillingRefusal
     */
    public function issue(mixed $root, array $args, GraphQLContext $context): Invoice
    {
        return $this->invoices->issue($this->viewer($context), Invoice::query()->findOrFail($args['id']), $args['issueDate'] ?? null);
    }

    /**
     * @param  array{id: string, paidAt: Carbon}  $args
     *
     * @throws BillingRefusal
     */
    public function markPaid(mixed $root, array $args): Invoice
    {
        return $this->invoices->markPaid(Invoice::query()->findOrFail($args['id']), $args['paidAt']);
    }

    /**
     * @param  array{id: string, reason: string}  $args
     *
     * @throws BillingRefusal
     */
    public function cancel(mixed $root, array $args): Invoice
    {
        return $this->invoices->cancel(Invoice::query()->findOrFail($args['id']), $args['reason']);
    }
}
