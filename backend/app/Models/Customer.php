<?php

namespace App\Models;

use App\Policies\BillingPolicy;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer of the billing (Auftrag 39). A project points at one; invoices go to one. Never deleted once it has
 * invoices, only archived. Content, not an account table. Only with billing.manage (BillingPolicy).
 *
 * @property int $id
 * @property string|null $company
 * @property string $name
 * @property string $street
 * @property string|null $house_number
 * @property string $postal_code
 * @property string $city
 * @property string $country
 * @property string|null $email
 * @property string $locale
 * @property string|null $notes
 * @property Carbon|null $archived_at
 */
#[UsePolicy(BillingPolicy::class)]
#[Fillable(['company', 'name', 'street', 'house_number', 'postal_code', 'city', 'country', 'email', 'locale', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The address as it stands today, the way an invoice freezes it.
     *
     * @return array{company: string|null, name: string, street: string, houseNumber: string|null, postalCode: string, city: string, country: string}
     */
    public function address(): array
    {
        return [
            'company' => $this->company,
            'name' => $this->name,
            'street' => $this->street,
            'houseNumber' => $this->house_number,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
