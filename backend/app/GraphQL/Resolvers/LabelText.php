<?php

namespace App\GraphQL\Resolvers;

use App\Models\Label;
use App\Models\LabelSet;

/**
 * Shown texts of label sets and labels: built-in ones are translated
 * (lang/de|en/labels.php), own ones as they were entered.
 */
class LabelText
{
    public function setName(LabelSet $set): string
    {
        return $set->displayName();
    }

    public function setDescription(LabelSet $set): ?string
    {
        return $set->displayDescription();
    }

    public function isBuiltin(LabelSet $set): bool
    {
        return $set->isBuiltin();
    }

    public function labelName(Label $label): string
    {
        return $label->displayName();
    }

    public function labelCategory(Label $label): ?string
    {
        return $label->displayCategory();
    }
}
