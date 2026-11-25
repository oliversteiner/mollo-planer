<?php

namespace App\Models;

use App\Policies\BillingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * The sender of the invoices and the terms (Auftrag 39 A), one row. current() creates it with the defaults the first
 * time. Only with billing.manage (BillingPolicy); changing it also needs a fresh password confirmation.
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $street
 * @property string|null $house_number
 * @property string|null $postal_code
 * @property string|null $city
 * @property string $country
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $iban
 * @property string|null $uid
 * @property int $payment_terms_days
 * @property string $number_format
 */
#[UsePolicy(BillingPolicy::class)]
#[Fillable(['name', 'street', 'house_number', 'postal_code', 'city', 'country', 'email', 'phone', 'website', 'iban', 'uid', 'payment_terms_days', 'number_format'])]
class BillingSetting extends Model
{
    /** What an invoice needs from the sender. The others are optional. */
    public const REQUIRED = ['name', 'street', 'postal_code', 'city', 'country', 'iban'];

    /** The defaults of the table, also on a model that was just created. */
    protected $attributes = ['country' => 'CH', 'payment_terms_days' => 30, 'number_format' => '{YYYY}-{NNNN}'];

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? self::query()->create([]);
    }

    /**
     * The required fields that are still empty, in camelCase as in GraphQL (for example postalCode, iban).
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        $missing = array_filter(self::REQUIRED, fn (string $field): bool => blank($this->getAttribute($field)));

        return array_values(array_map(fn (string $field): string => lcfirst(str_replace('_', '', ucwords($field, '_'))), $missing));
    }

    /**
     * The sender as an invoice freezes it.
     *
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'street' => $this->street,
            'houseNumber' => $this->house_number,
            'postalCode' => $this->postal_code,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'iban' => $this->iban,
            'uid' => $this->uid,
        ];
    }
}
