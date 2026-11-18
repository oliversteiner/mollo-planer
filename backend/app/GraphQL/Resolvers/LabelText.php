<?php

namespace App\GraphQL\Resolvers;

use App\Models\Label;

/**
 * Shown texts of a label: the name in the language of the request (else of
 * the project), all names, and the category as it is shown.
 */
class LabelText
{
    public function labelName(Label $label): string
    {
        return $label->displayName();
    }

    /**
     * @return list<array{locale: string, name: string}>
     */
    public function labelNames(Label $label): array
    {
        return self::namesList((array) $label->names);
    }

    public function labelCategory(Label $label): ?string
    {
        return $label->displayCategory();
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{locale: string, name: string}>
     */
    public static function namesList(array $names): array
    {
        return array_map(static fn (string $locale, string $name): array => ['locale' => $locale, 'name' => $name], array_keys($names), array_values($names));
    }
}
