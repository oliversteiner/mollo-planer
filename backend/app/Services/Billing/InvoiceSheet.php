<?php

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Billing\CreditorReference;
use Illuminate\Support\Carbon;

/**
 * What an invoice PDF shows (Auftrag 39 C). An issued invoice shows its frozen copies; a draft (preview) the
 * customer and sender of today; the sample (Auftrag 39 F) made-up data. Nothing here reads the database once made.
 */
final readonly class InvoiceSheet
{
    /**
     * @param  array<string, string|null>  $sender
     * @param  array<string, string|null>  $recipient
     * @param  list<array{projectName: string, title: string, description: string|null, amountCents: int}>  $items
     */
    public function __construct(
        public ?string $number,
        public Carbon $issueDate,
        public Carbon $dueDate,
        public int $paymentTermsDays,
        public array $sender,
        public array $recipient,
        public string $locale,
        public array $items,
        public int $totalCents,
        public ?string $reference,
        public ?string $notes,
        public bool $draft,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        $settings = BillingSetting::current();
        $issued = ! $invoice->isDraft();
        $issueDate = $invoice->issue_date ?? Carbon::today();
        $terms = $issued && $invoice->due_date !== null ? (int) $issueDate->diffInDays($invoice->due_date) : $settings->payment_terms_days;

        return new self(
            number: $invoice->number,
            issueDate: $issueDate,
            dueDate: $invoice->due_date ?? $issueDate->copy()->addDays($terms),
            paymentTermsDays: $terms,
            sender: $issued && $invoice->sender !== null ? $invoice->sender : $settings->snapshot(),
            recipient: $issued && $invoice->recipient !== null ? $invoice->recipient : Customer::query()->findOrFail($invoice->customer_id)->address(),
            locale: $invoice->locale,
            items: $invoice->items()->get()->map(fn (InvoiceItem $item): array => [
                'projectName' => $item->project_name,
                'title' => $item->title,
                'description' => $item->description,
                'amountCents' => $item->amount_cents,
            ])->values()->all(),
            totalCents: $invoice->total_cents,
            reference: $invoice->reference,
            notes: $invoice->notes,
            draft: ! $issued,
        );
    }

    /**
     * Made-up data for the sample invoice: the sender of the settings (made-up where empty), an invented customer,
     * items of some projects. No watermark: the sample is also the file for the validation portal of SIX.
     */
    public static function sample(string $locale = 'de', int $items = 5, int $projects = 2): self
    {
        $settings = BillingSetting::current();
        $sender = $settings->snapshot();

        foreach (['name' => 'Muster GmbH', 'street' => 'Musterweg', 'houseNumber' => '1', 'postalCode' => '3000', 'city' => 'Bern', 'country' => 'CH', 'iban' => 'CH9300762011623852957'] as $key => $value) {
            $sender[$key] = filled($sender[$key] ?? null) ? $sender[$key] : $value;
        }

        $list = [];

        for ($i = 1; $i <= $items; $i++) {
            $list[] = [
                'projectName' => ($locale === 'en' ? 'Sample project ' : 'Musterprojekt ').(intdiv(($i - 1) * $projects, $items) + 1),
                'title' => ($locale === 'en' ? 'Milestone ' : 'Meilenstein ').$i,
                'description' => $locale === 'en'
                    ? 'Design, implementation and tests of the agreed scope, handed over and accepted.'
                    : 'Konzept, Umsetzung und Tests des vereinbarten Umfangs, übergeben und abgenommen.',
                'amountCents' => 120000 + $i * 5000,
            ];
        }

        $today = Carbon::today();

        return new self(
            number: $today->year.'-0042',
            issueDate: $today,
            dueDate: $today->copy()->addDays($settings->payment_terms_days),
            paymentTermsDays: $settings->payment_terms_days,
            sender: $sender,
            recipient: ['company' => 'Beispiel AG', 'name' => 'Erika Beispiel', 'street' => 'Beispielstrasse', 'houseNumber' => '12a', 'postalCode' => '8004', 'city' => 'Zürich', 'country' => 'CH'],
            locale: $locale,
            items: $list,
            totalCents: array_sum(array_column($list, 'amountCents')),
            reference: CreditorReference::fromNumber($today->year.'-0042'),
            notes: $locale === 'en' ? 'Thank you for the good cooperation.' : 'Herzlichen Dank für die gute Zusammenarbeit.',
            draft: false,
        );
    }

    /**
     * Items grouped by project, the projects in the order they first come.
     *
     * @return list<array{projectName: string, items: list<array{projectName: string, title: string, description: string|null, amountCents: int}>, totalCents: int}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            $groups[$item['projectName']] ??= ['projectName' => $item['projectName'], 'items' => [], 'totalCents' => 0];
            $groups[$item['projectName']]['items'][] = $item;
            $groups[$item['projectName']]['totalCents'] += $item['amountCents'];
        }

        $groups = array_values($groups);

        return $groups;
    }
}
