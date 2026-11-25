<?php

namespace App\GraphQL\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Services\Billing\InvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Reading the billing (Auftrag 39 B), checked by @can (billing.manage). A fixed number of queries, whatever the
 * number of projects and invoices.
 */
class InvoiceQueries
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * @param  array{customerId?: string|null}  $args
     * @return array{groups: list<array<string, mixed>>, withoutCustomer: list<array<string, mixed>>, withoutPrice: list<array<string, mixed>>}
     */
    public function overview(mixed $root, array $args): array
    {
        $customerId = isset($args['customerId']) ? (int) $args['customerId'] : null;
        $groups = [];

        foreach ($this->invoices->billableMilestones($customerId)->groupBy(fn (Milestone $milestone): int => (int) $milestone->project->customer_id) as $milestones) {
            $projects = [];

            foreach ($milestones->groupBy('project_id') as $ofProject) {
                $projects[] = [
                    'projectId' => (string) $ofProject->first()->project_id,
                    'projectName' => $ofProject->first()->project->name,
                    'totalCents' => (int) $ofProject->sum('price_cents'),
                    'milestones' => $ofProject->map(fn (Milestone $milestone): array => [
                        'id' => (string) $milestone->id,
                        'title' => $milestone->title,
                        'priceCents' => (int) $milestone->price_cents,
                        'dueDate' => $milestone->due_date,
                        'releaseVersion' => $milestone->release_version,
                    ])->values()->all(),
                ];
            }

            /** @var Customer $customer */
            $customer = $milestones->first()->project->customer;
            $groups[] = ['customer' => $customer, 'totalCents' => (int) $milestones->sum('price_cents'), 'projects' => $projects];
        }

        return [
            'groups' => $groups,
            'withoutCustomer' => $customerId === null ? $this->hints(fn (Builder $query) => $query->whereHas('project', fn (Builder $project) => $project->whereNull('customer_id'))) : [],
            'withoutPrice' => $this->hints(fn (Builder $query) => $query->whereNull('price_cents')->whereHas('project', fn (Builder $project) => $customerId === null ? $project->whereNotNull('customer_id') : $project->where('customer_id', $customerId))),
        ];
    }

    /**
     * @param  array{status?: string|null, customerId?: string|null, year?: int|null}  $args
     * @return Collection<int, Invoice>
     */
    public function invoices(mixed $root, array $args): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->when(isset($args['status']), fn (Builder $query) => $query->where('status', $args['status']))
            ->when(isset($args['customerId']), fn (Builder $query) => $query->where('customer_id', (int) $args['customerId']))
            ->when(isset($args['year']), fn (Builder $query) => $query->whereBetween('issue_date', [Carbon::create((int) $args['year'])->startOfYear()->toDateString(), Carbon::create((int) $args['year'])->endOfYear()->toDateString()]))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [InvoiceStatus::DRAFT->value])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{openCount: int, openCents: int, overdueCount: int, overdueCents: int}
     */
    public function totals(): array
    {
        $open = Invoice::query()->where('status', InvoiceStatus::ISSUED->value);
        $overdue = (clone $open)->where('due_date', '<', Carbon::today()->toDateString());

        return [
            'openCount' => (clone $open)->count(),
            'openCents' => (int) (clone $open)->sum('total_cents'),
            'overdueCount' => (clone $overdue)->count(),
            'overdueCents' => (int) (clone $overdue)->sum('total_cents'),
        ];
    }

    public function hasPdf(Invoice $invoice): bool
    {
        return $invoice->pdf_path !== null && Storage::disk('local')->exists($invoice->pdf_path);
    }

    /**
     * Done milestones no invoice holds, narrowed by $where, as hints.
     *
     * @param  callable(Builder<Milestone>): mixed  $where
     * @return list<array{projectId: string, projectName: string, milestoneId: string, milestoneTitle: string}>
     */
    private function hints(callable $where): array
    {
        $query = Milestone::query()
            ->where('status', ItemStatus::DONE->value)
            ->whereNotExists(fn ($held) => $held->selectRaw('1')->from('invoice_items')->whereColumn('invoice_items.billing_milestone_id', 'milestones.id'))
            ->with('project');
        $where($query);

        return $query->get()
            ->sortBy(fn (Milestone $milestone): array => [mb_strtolower($milestone->project->name), $milestone->position])
            ->map(fn (Milestone $milestone): array => [
                'projectId' => (string) $milestone->project_id,
                'projectName' => $milestone->project->name,
                'milestoneId' => (string) $milestone->id,
                'milestoneTitle' => $milestone->title,
            ])->values()->all();
    }
}
