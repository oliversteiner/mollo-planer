<?php

namespace App\Enums;

/**
 * The kinds of a document (docs/dokumentenverwaltung.md §3): a fixed list. The texts are in lang/de and lang/en
 * (documents.kinds).
 */
enum DocumentKind: string
{
    case PROCEDURE = 'procedure';
    case MINUTES = 'minutes';
    case ANALYSIS = 'analysis';
    case CONCEPT = 'concept';
    case OTHER = 'other';

    public function label(?string $locale = null): string
    {
        return __('documents.kinds.'.$this->value, [], $locale);
    }
}
