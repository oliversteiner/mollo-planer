<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\GraphQL\Errors\BillingRefusal;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Milestone;
use App\Models\User;
use App\Support\Billing\CreditorReference;
use App\Support\Billing\InvoiceNumber;
use App\Support\RichTextDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Every step of an invoice (Auftrag 39 B): draft, change, delete, issue, paid, cancel. The resolvers only call this;
 * billing.manage is checked before (@can).
 *
 * - Billable: the milestone is done, has a price, is not in the trash, its project has a customer, and no invoice holds
 *   it (draft, issued, paid). The unique column invoice_items.billing_milestone_id makes the last rule hold also when
 *   two drafts are made at the same moment.
 * - One invoice, one customer: every milestone must belong to a project of the customer of the invoice.
 * - Issuing is final: the number comes from the number range with a row lock (no gaps, unique), addresses, project
 *   names and items are frozen, the PDF is rendered once and kept on the private disk. All in one transaction: if
 *   anything fails, nothing is stored and the number is not used.
 */
final class InvoiceService
{
    /** Length of the item text made from the description of the milestone. */
    public const DESCRIPTION_LENGTH = 600;

    /** Highest amount of a QR bill, in Rappen (CHF 9 999 999.99). */
    public const MAX_TOTAL_CENTS = 999_999_999;

    public function __construct(private readonly InvoicePdfRenderer $pdf) {}

    /**
     * Milestones that can go on an invoice, with project and customer loaded, ordered by customer, project and
     * position. Two queries whatever the number of projects.
     *
     * @return Collection<int, Milestone>
     */
    public function billableMilestones(?int $customerId = null): Collection
    {
        return self::billable(Milestone::query())
            ->when($customerId !== null, fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->where('customer_id', $customerId)))
            ->with(['project.customer'])
            ->get()
            ->sortBy(fn (Milestone $milestone): array => [
                mb_strtolower((string) ($milestone->project->customer->company ?? $milestone->project->customer->name)),
                mb_strtolower($milestone->project->name),
                $milestone->position,
                $milestone->id,
            ])
            ->values();
    }

    /**
     * The rule "billable" as a query on milestones.
     *
     * @param  Builder<Milestone>  $query
     * @return Builder<Milestone>
     */
    public static function billable(Builder $query): Builder
    {
        return $query
            ->where('status', ItemStatus::DONE->value)
            ->whereNotNull('price_cents')
            ->whereHas('project', fn (Builder $project) => $project->whereNotNull('customer_id'))
            ->whereNotExists(fn ($held) => $held->selectRaw('1')->from('invoice_items')->whereColumn('invoice_items.billing_milestone_id', 'milestones.id'));
    }

    /**
     * @param  list<int|string>  $milestoneIds
     *
     * @throws BillingRefusal
     */
    public function createDraft(User $user, Customer $customer, array $milestoneIds, ?string $notes = null): Invoice
    {
        $milestones = $this->checkedMilestones($customer, $milestoneIds);

        if ((int) $milestones->sum('price_cents') > self::MAX_TOTAL_CENTS) {
            throw new BillingRefusal(BillingRefusal::TOTAL_TOO_HIGH);
        }

        return $this->holding(fn (): Invoice => DB::transaction(function () use ($customer, $milestones, $notes): Invoice {
            $invoice = new Invoice;
            $invoice->forceFill([
                'customer_id' => $customer->id,
                'status' => InvoiceStatus::DRAFT,
                'locale' => $customer->locale,
                'notes' => self::text($notes),
            ])->save();

            foreach ($milestones->values() as $position => $milestone) {
                $item = new InvoiceItem;
                $item->forceFill([
                    'invoice_id' => $invoice->id,
                    'project_id' => $milestone->project_id,
                    'project_name' => $milestone->project->name,
                    'milestone_id' => $milestone->id,
                    'billing_milestone_id' => $milestone->id,
                    'position' => $position,
                    'title' => $milestone->title,
                    'description' => self::text(RichTextDocument::excerpt($milestone->description, self::DESCRIPTION_LENGTH)),
                    'amount_cents' => (int) $milestone->price_cents,
                ])->save();
            }

            return $this->recalculate($invoice);
        }), $milestones->pluck('id')->all());
    }

    /**
     * Notes and the items of a draft: $items is the list to keep, in this order, with their texts. Items not in it are
     * removed (their milestones are billable again).
     *
     * @param  list<array{id: int|string, description?: string|null}>|null  $items
     *
     * @throws BillingRefusal
     */
    public function updateDraft(Invoice $invoice, ?string $notes, ?array $items, bool $notesGiven = true): Invoice
    {
        return DB::transaction(function () use ($invoice, $notes, $items, $notesGiven): Invoice {
            $invoice = $this->lockedDraft($invoice);

            if ($notesGiven) {
                $invoice->forceFill(['notes' => self::text($notes)])->save();
            }

            if ($items !== null) {
                $current = $invoice->items()->get()->keyBy('id');
                $keep = collect($items)->filter(fn (array $item): bool => $current->has((int) $item['id']))->values();

                if ($keep->isEmpty()) {
                    throw new BillingRefusal(BillingRefusal::EMPTY);
                }

                $invoice->items()->whereNotIn('id', $keep->pluck('id')->map(fn ($id): int => (int) $id)->all())->delete();

                foreach ($keep as $position => $item) {
                    $model = $current->get((int) $item['id']);
                    $model->forceFill(['position' => $position]);

                    if (array_key_exists('description', $item)) {
                        $model->forceFill(['description' => self::text($item['description'])]);
                    }

                    $model->save();
                }
            }

            return $this->recalculate($invoice);
        });
    }

    /**
     * @throws BillingRefusal
     */
    public function deleteDraft(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = $this->lockedDraft($invoice);
            $invoice->delete();

            return $invoice;
        });
    }

    /**
     * Gives the number, freezes the invoice and keeps its PDF.
     *
     * @throws BillingRefusal
     */
    public function issue(User $user, Invoice $invoice, ?Carbon $issueDate = null): Invoice
    {
        $written = null;

        try {
            return DB::transaction(function () use ($user, $invoice, $issueDate, &$written): Invoice {
                $invoice = $this->lockedDraft($invoice);
                $settings = BillingSetting::current();

                if (($missing = $settings->missingFields()) !== []) {
                    throw new BillingRefusal(BillingRefusal::SENDER_INCOMPLETE, ['missingFields' => $missing]);
                }

                $items = $invoice->items()->with(['milestone', 'project'])->get();

                if ($items->isEmpty()) {
                    throw new BillingRefusal(BillingRefusal::EMPTY);
                }

                $gone = $items->filter(fn (InvoiceItem $item): bool => $item->milestone === null || $item->milestone->trashed() || $item->milestone->status !== ItemStatus::DONE);

                if ($gone->isNotEmpty()) {
                    throw new BillingRefusal(BillingRefusal::NOT_BILLABLE, ['milestoneIds' => $gone->map(fn (InvoiceItem $item): string => (string) $item->milestone_id)->values()->all(), 'titles' => $gone->pluck('title')->all()]);
                }

                $date = ($issueDate ?? Carbon::today())->copy()->startOfDay();
                $number = $this->nextNumber($settings->number_format, (int) $date->year);
                $customer = Customer::query()->findOrFail($invoice->customer_id);

                foreach ($items as $item) {
                    if ($item->project !== null) {
                        $item->forceFill(['project_name' => $item->project->name])->save();
                    }
                }

                $invoice->forceFill([
                    'number' => $number,
                    'reference' => CreditorReference::fromNumber($number),
                    'status' => InvoiceStatus::ISSUED,
                    'issue_date' => $date,
                    'due_date' => $date->copy()->addDays($settings->payment_terms_days),
                    'currency' => 'CHF',
                    'recipient' => $customer->address(),
                    'sender' => $settings->snapshot(),
                    'locale' => $customer->locale,
                    'issued_by' => $user->id,
                    'issued_at' => now(),
                ]);
                $this->recalculate($invoice);

                $path = 'invoices/'.$date->year.'/'.preg_replace('/[^A-Za-z0-9_-]/', '_', $number).'.pdf';

                if (! Storage::disk('local')->put($path, $this->pdf->render($invoice))) {
                    throw new RuntimeException('The PDF of invoice '.$number.' could not be stored.');
                }

                $written = $path;
                $invoice->forceFill(['pdf_path' => $path])->save();

                return $invoice;
            });
        } catch (Throwable $error) {
            if ($written !== null) {
                Storage::disk('local')->delete($written);
            }

            throw $error;
        }
    }

    /**
     * @throws BillingRefusal
     */
    public function markPaid(Invoice $invoice, Carbon $paidAt): Invoice
    {
        return DB::transaction(function () use ($invoice, $paidAt): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== InvoiceStatus::ISSUED) {
                throw new BillingRefusal(BillingRefusal::WRONG_STATUS, ['status' => $invoice->status->value]);
            }

            $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_at' => $paidAt->copy()->startOfDay()])->save();

            return $invoice;
        });
    }

    /**
     * Cancels an issued invoice: the number stays taken, the milestones are billable again.
     *
     * @throws BillingRefusal
     */
    public function cancel(Invoice $invoice, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status !== InvoiceStatus::ISSUED) {
                throw new BillingRefusal(BillingRefusal::WRONG_STATUS, ['status' => $invoice->status->value]);
            }

            $invoice->forceFill(['status' => InvoiceStatus::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => trim($reason)])->save();
            InvoiceItem::query()->where('invoice_id', $invoice->id)->update(['billing_milestone_id' => null]);

            return $invoice;
        });
    }

    /**
     * The next number of the range, under a row lock that holds until the transaction ends: two invoices issued at the
     * same time wait for each other, a failed issue gives its number back with the rollback.
     *
     * @throws BillingRefusal
     */
    private function nextNumber(string $format, int $year): string
    {
        $range = InvoiceNumber::rangeOf($format, $year);
        DB::table('invoice_number_sequences')->insertOrIgnore(['year' => $range, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $last = (int) DB::table('invoice_number_sequences')->where('year', $range)->lockForUpdate()->value('last_number');
        $number = InvoiceNumber::make($format, $year, $last + 1);

        if (strlen((string) preg_replace('/[^A-Za-z0-9]/', '', $number)) > 21) {
            throw new BillingRefusal(BillingRefusal::NUMBER_TOO_LONG, ['number' => $number]);
        }

        DB::table('invoice_number_sequences')->where('year', $range)->update(['last_number' => $last + 1, 'updated_at' => now()]);

        return $number;
    }

    /**
     * The milestones of a new draft, each checked: billable, and in a project of this customer.
     *
     * @param  list<int|string>  $milestoneIds
     * @return Collection<int, Milestone>
     *
     * @throws BillingRefusal
     */
    private function checkedMilestones(Customer $customer, array $milestoneIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $milestoneIds)));

        if ($ids === []) {
            throw new BillingRefusal(BillingRefusal::EMPTY);
        }

        $found = Milestone::query()->whereKey($ids)->with('project')->get();
        $missing = array_values(array_diff($ids, $found->modelKeys()));

        if ($missing !== []) {
            throw new BillingRefusal(BillingRefusal::NOT_BILLABLE, ['milestoneIds' => array_map('strval', $missing), 'titles' => []]);
        }

        $withoutCustomer = $found->filter(fn (Milestone $milestone): bool => $milestone->project?->customer_id === null);

        if ($withoutCustomer->isNotEmpty()) {
            throw new BillingRefusal(BillingRefusal::CUSTOMER_MISSING, ['projects' => $withoutCustomer->map(fn (Milestone $milestone): string => (string) $milestone->project?->name)->unique()->values()->all()]);
        }

        $other = $found->filter(fn (Milestone $milestone): bool => (int) $milestone->project->customer_id !== $customer->id);

        if ($other->isNotEmpty()) {
            throw new BillingRefusal(BillingRefusal::OTHER_CUSTOMER, ['milestoneIds' => array_map('strval', $other->modelKeys()), 'titles' => $other->pluck('title')->all()]);
        }

        $billable = self::billable(Milestone::query())->whereKey($ids)->pluck('id')->all();
        $not = $found->reject(fn (Milestone $milestone): bool => in_array($milestone->id, $billable, true));

        if ($not->isNotEmpty()) {
            throw new BillingRefusal(BillingRefusal::NOT_BILLABLE, ['milestoneIds' => array_map('strval', $not->modelKeys()), 'titles' => $not->pluck('title')->all()]);
        }

        return $found->sortBy(fn (Milestone $milestone): array => [mb_strtolower($milestone->project->name), $milestone->project_id, $milestone->position, $milestone->id])->values();
    }

    /**
     * A milestone taken by another draft in the same moment hits the unique column: the same refusal as when it was
     * taken before.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @param  list<int>  $milestoneIds
     * @return T
     *
     * @throws BillingRefusal
     */
    private function holding(callable $write, array $milestoneIds): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            $held = InvoiceItem::query()->whereIn('billing_milestone_id', $milestoneIds)->pluck('billing_milestone_id')->all();

            throw new BillingRefusal(BillingRefusal::NOT_BILLABLE, ['milestoneIds' => array_map('strval', $held), 'titles' => []]);
        }
    }

    /**
     * @throws BillingRefusal
     */
    private function lockedDraft(Invoice $invoice): Invoice
    {
        $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

        if (! $locked->isDraft()) {
            throw new BillingRefusal(BillingRefusal::WRONG_STATUS, ['status' => $locked->status->value]);
        }

        return $locked;
    }

    private function recalculate(Invoice $invoice): Invoice
    {
        $invoice->forceFill(['total_cents' => (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->sum('amount_cents')])->save();

        return $invoice;
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
