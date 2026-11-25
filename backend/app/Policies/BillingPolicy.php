<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\User;
use App\Support\Rights;

/**
 * Billing (Auftrag 39): customers, prices, invoices, the sender. Everything needs the system right billing.manage,
 * independent of any role in a project (an owner without it sees no price and no customer). Agents never have it
 * (Rights::neverForAgents). Registered for Customer and BillingSetting (and Invoice in part B).
 */
class BillingPolicy
{
    public function manage(User $user): bool
    {
        return Rights::allows($user, Right::BILLING_MANAGE);
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    public function view(User $user): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user): bool
    {
        return $this->manage($user);
    }
}
