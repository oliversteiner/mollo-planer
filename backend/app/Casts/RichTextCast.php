<?php

namespace App\Casts;

use App\Support\RichTextDocument;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores TipTap documents as JSON. Empty documents become null, links keep
 * only their href. Refuses documents outside the whitelist, so nothing
 * invalid reaches the database even if input validation was skipped.
 *
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class RichTextCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (RichTextDocument::validate($value) !== null) {
            throw new InvalidArgumentException("Attribute [{$key}] is not an allowed rich text document.");
        }

        $normalized = RichTextDocument::normalize($value);

        return $normalized === null ? null : json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
