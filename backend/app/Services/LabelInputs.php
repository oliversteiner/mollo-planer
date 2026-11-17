<?php

namespace App\Services;

use App\Enums\LabelColor;
use App\Enums\Locale;
use App\Planer\TemplateContent;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks the labels a person sends for a project or a template and brings
 * them to one shape: the key (kept for a label that exists, made from the name
 * in the language of the project or template for a new one), the names by
 * language, the category and the colour.
 */
final class LabelInputs
{
    /**
     * @param  list<array<string, mixed>>  $labels  key?, names [{locale, name}], category?, color
     * @param  list<string>  $existingKeys  The keys that exist already; others are not accepted
     * @param  string  $locale  Language of the project or template: its name is required
     * @param  string  $field  Where an error is shown, for example "labels" or "input.labels"
     * @return list<array{key: string, names: array<string, string>, category: ?string, color: string}>
     *
     * @throws ValidationException
     */
    public static function normalize(array $labels, array $existingKeys, string $locale, string $field, int $min = 0): array
    {
        if (count($labels) > TemplateContent::MAX_LABELS) {
            throw ValidationException::withMessages([$field => __('labels.too_many', ['max' => TemplateContent::MAX_LABELS])]);
        }
        if (count($labels) < $min) {
            throw ValidationException::withMessages([$field => __('labels.at_least_one')]);
        }

        $out = [];
        $keys = [];
        $names = [];

        foreach (array_values($labels) as $index => $label) {
            $where = "{$field}.{$index}";
            $labelNames = self::names($label['names'] ?? [], $locale, $where);

            foreach ($labelNames as $language => $name) {
                $lower = mb_strtolower($name);

                if (isset($names[$language][$lower])) {
                    throw ValidationException::withMessages(["{$where}.names" => __('labels.duplicate_name')]);
                }
                $names[$language][$lower] = true;
            }

            $key = isset($label['key']) && $label['key'] !== '' ? (string) $label['key'] : null;

            if ($key !== null && ! in_array($key, $existingKeys, true)) {
                throw ValidationException::withMessages(["{$where}.key" => __('labels.key_unknown')]);
            }
            if ($key !== null && isset($keys[$key])) {
                throw ValidationException::withMessages(["{$where}.key" => __('labels.key_twice')]);
            }
            if ($key !== null) {
                $keys[$key] = true;
            }

            $category = isset($label['category']) ? trim((string) $label['category']) : '';

            $out[] = [
                'key' => $key ?? '',
                'names' => $labelNames,
                'category' => $category === '' ? null : mb_substr($category, 0, 40),
                'color' => $label['color'] instanceof LabelColor ? $label['color']->value : (string) $label['color'],
            ];
        }

        // New labels get their key at last, so none takes the key of one that stays.
        foreach ($out as $index => $label) {
            if ($label['key'] === '') {
                $out[$index]['key'] = self::freshKey($label['names'][$locale], [...$existingKeys, ...array_column($out, 'key')]);
            }
        }

        return $out;
    }

    /**
     * A key from a name: a-z, 0-9, _ and -, at most 40 characters, not among the taken ones.
     *
     * @param  list<string>  $taken
     */
    public static function freshKey(string $name, array $taken): string
    {
        $base = mb_substr(Str::slug($name, '_'), 0, 40);
        $base = $base === '' ? 'label' : $base;
        $key = $base;

        for ($number = 2; in_array($key, $taken, true); $number++) {
            $key = mb_substr($base, 0, 40 - strlen("_{$number}"))."_{$number}";
        }

        return $key;
    }

    /**
     * @param  list<array{locale: string, name: string}>|array<string, string>  $input
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private static function names(array $input, string $locale, string $where): array
    {
        $names = [];

        foreach ($input as $language => $entry) {
            [$code, $name] = is_array($entry) ? [(string) $entry['locale'], $entry['name']] : [(string) $language, $entry];
            $name = trim((string) $name);

            if (Locale::tryFrom($code) === null || $name === '' || mb_strlen($name) > TemplateContent::NAME_MAX) {
                throw ValidationException::withMessages(["{$where}.names" => __('labels.name_invalid')]);
            }
            if (isset($names[$code])) {
                throw ValidationException::withMessages(["{$where}.names" => __('labels.language_twice')]);
            }

            $names[$code] = $name;
        }

        if (! isset($names[$locale])) {
            throw ValidationException::withMessages(["{$where}.names" => __('labels.name_missing', ['language' => strtoupper($locale)])]);
        }

        return $names;
    }
}
