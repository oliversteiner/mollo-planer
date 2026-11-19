<?php

namespace App\Rules;

use App\Support\DocumentContent;
use App\Support\InvalidDocumentContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The content of a document (TipTap JSON from the editor): the profile "Document" and at most 200 000
 * characters of Markdown (docs/dokumentenverwaltung.md §5.3). The error is on the field, in the language of the
 * request.
 */
class DocumentContentRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('richtext.not_a_document')->translate();

            return;
        }

        try {
            DocumentContent::markdownFromJson($value);
        } catch (InvalidDocumentContent $error) {
            $fail('richtext.'.$error->key)->translate($error->parameters);
        }
    }
}
