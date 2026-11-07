<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Format of a Font Awesome icon name without style, e.g. "rocket" or
 * "rocket-launch". Whether the icon exists is up to the frontend catalog.
 */
class FontAwesomeIconRule implements ValidationRule
{
    public const MAX_LENGTH = 60;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)
            || strlen($value) > self::MAX_LENGTH
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            $fail('icon.invalid')->translate(['max' => self::MAX_LENGTH]);
        }
    }
}
