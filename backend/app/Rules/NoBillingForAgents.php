<?php

namespace App\Rules;

use App\Enums\Right;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An agent account never gets a system role that carries billing.manage (Auftrag 39): prices, customers and invoices
 * never reach an agent or the connector. Rights::neverForAgents holds anyway; this tells the person who assigns the role.
 */
class NoBillingForAgents implements ValidationRule
{
    public function __construct(private readonly ?User $person) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->person === null || ! $this->person->isAgent()) {
            return;
        }

        $role = Role::resolve(Role::SCOPE_SYSTEM, (string) $value);

        if ($role !== null && in_array(Right::BILLING_MANAGE, Rights::rightsOfRole($role), true)) {
            $fail(__('billing.agent_role', ['role' => $role->name(app()->getLocale())]));
        }
    }
}
