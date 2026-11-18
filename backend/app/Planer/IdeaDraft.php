<?php

namespace App\Planer;

use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * The draft of an idea (docs/auftrag-m6.md §2.3): a planer-v2 excerpt. It is checked against the schema and
 * the rules of the format when it is saved, not against a project: the anchors are checked when the idea is
 * put into a project. It is stored in canonical form.
 */
final class IdeaDraft
{
    /**
     * @throws ValidationException With the path of the first problem, at "input.draft"
     */
    public static function normalize(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        try {
            $document = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValidationException::withMessages(['input.draft' => __('planer.import.invalid', ['message' => $error->getMessage()])]);
        }

        if (! is_array($document)) {
            throw ValidationException::withMessages(['input.draft' => __('planer.import.invalid', ['message' => 'not an object'])]);
        }

        $issues = Validator::errors($document);

        if ($issues !== []) {
            throw ValidationException::withMessages(['input.draft' => __('planer.import.invalid', ['message' => "{$issues[0]['path']}: {$issues[0]['message']}"])]);
        }

        return Canonical::text($document);
    }
}
