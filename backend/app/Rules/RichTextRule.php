<?php

namespace App\Rules;

use App\Support\RichTextDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Validation\Validator;

/**
 * Validates a TipTap document against the whitelist in RichTextDocument.
 *
 * An empty document is fine for descriptions (stored as null). If the
 * attribute is also "required" (comment body), it must contain text.
 */
class RichTextRule implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $error = RichTextDocument::validate($value);

        if ($error !== null) {
            [$key, $parameters] = $error;
            $fail('richtext.'.$key)->translate($parameters);

            return;
        }

        if ($this->isRequired($attribute) && RichTextDocument::isEmpty($value)) {
            $fail('richtext.empty')->translate();
        }
    }

    private function isRequired(string $attribute): bool
    {
        return $this->validator?->hasRule($attribute, ['Required']) ?? false;
    }
}
