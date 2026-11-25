<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Postal code fitting the country next to it (Auftrag 39 A): four digits in Switzerland and Liechtenstein, elsewhere
 * letters, digits, spaces and hyphens. The country is the field of the given name next to the postal code (the same
 * prefix, for example input.country for input.postalCode).
 */
class PostalCode implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $countryKey, private readonly ?string $fallbackCountry = 'CH') {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $prefix = str_contains($attribute, '.') ? substr($attribute, 0, (int) strrpos($attribute, '.') + 1) : '';
        $country = strtoupper((string) (Arr::get($this->data, $prefix.$this->countryKey) ?? $this->fallbackCountry));
        $pattern = in_array($country, ['CH', 'LI'], true) ? '/^\d{4}$/' : '/^[A-Za-z0-9][A-Za-z0-9 \-]{1,15}$/';

        if (preg_match($pattern, $value) !== 1) {
            $fail(__('billing.postal_code', ['country' => $country]));
        }
    }
}
