<?php

namespace App\Rules;

use App\Planer\PlanerLimits;
use App\Planer\RichText\TiptapToMarkdown;
use Closure;

/**
 * The rule for the description of a project, milestone, todo or task: the
 * whitelist of RichTextRule, and at most PlanerLimits::DESCRIPTION_MARKDOWN
 * characters as canonical Markdown, so that the planer file takes it
 * (spec/format-v2.md §8). Comments keep RichTextRule.
 */
class DescriptionRule extends RichTextRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $failed = false;
        parent::validate($attribute, $value, function (string $message) use ($fail, &$failed) {
            $failed = true;

            return $fail($message);
        });

        if ($failed || ! is_array($value)) {
            return;
        }

        $length = mb_strlen(TiptapToMarkdown::convert($value));

        if ($length > PlanerLimits::DESCRIPTION_MARKDOWN) {
            $fail('richtext.markdown_too_long')->translate(['max' => PlanerLimits::DESCRIPTION_MARKDOWN, 'length' => $length]);
        }
    }
}
