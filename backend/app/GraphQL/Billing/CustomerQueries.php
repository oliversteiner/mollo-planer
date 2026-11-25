<?php

namespace App\GraphQL\Billing;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Customers of the billing (Auftrag 39 A), checked by @can (billing.manage).
 */
class CustomerQueries
{
    /**
     * @param  array{archived?: bool|null}  $args
     * @return Collection<int, Customer>
     */
    public function customers(mixed $root, array $args): Collection
    {
        return Customer::query()
            ->when((bool) ($args['archived'] ?? false), fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->orderByRaw('COALESCE(company, name)')
            ->orderBy('name')
            ->get();
    }
}
