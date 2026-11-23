<?php

namespace App\Tools\Support;

use Illuminate\Validation\ValidationException;

final class McpCursor
{
    /**
     * @param  array<string, int|string>  $values
     */
    public static function encode(array $values): string
    {
        return rtrim(strtr(base64_encode(json_encode($values, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, int|string>|null
     *
     * @throws ValidationException
     */
    public static function decode(?string $cursor, array $keys): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/').str_repeat('=', (4 - strlen($cursor) % 4) % 4), true);
        $values = $decoded === false ? null : json_decode($decoded, true);

        if (! is_array($values) || array_diff($keys, array_keys($values)) !== [] || collect($values)->contains(fn ($value) => ! is_int($value) && ! is_string($value))) {
            throw ValidationException::withMessages(['cursor' => 'The cursor is invalid.']);
        }

        return array_intersect_key($values, array_flip($keys));
    }
}
