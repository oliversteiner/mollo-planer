<?php

namespace App\GraphQL\Resolvers;

use App\Models\Label;
use App\Models\Template;

/**
 * Shown texts and parts of a template: name and description (translated for
 * built-in ones), the labels with their names, and the outline.
 */
class TemplateText
{
    public function name(Template $template): string
    {
        return $template->displayName();
    }

    public function description(Template $template): ?string
    {
        return $template->displayDescription();
    }

    public function isBuiltin(Template $template): bool
    {
        return $template->isBuiltin();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function labels(Template $template): array
    {
        return array_map(static function (array $label) use ($template): array {
            $name = null;
            foreach ([app()->getLocale(), $template->locale, 'de', 'en'] as $locale) {
                if (($label['names'][$locale] ?? '') !== '') {
                    $name = $label['names'][$locale];

                    break;
                }
            }

            return [
                'key' => $label['key'],
                'name' => $name ?? $label['key'],
                'names' => LabelText::namesList($label['names']),
                'category' => $label['category'],
                'categoryLabel' => Label::translateCategory($label['category']),
                'color' => $label['color'],
            ];
        }, $template->labels());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outline(Template $template): array
    {
        return $template->outline();
    }
}
