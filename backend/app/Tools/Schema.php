<?php

namespace App\Tools;

/**
 * The input of a tool as a plain JSON Schema (an array), not as an object of one library: the adapters read the same
 * array. Small helpers for what the tools need.
 */
final class Schema
{
    /**
     * @param  list<string>|null  $enum
     * @return array<string, mixed>
     */
    public static function string(?string $description = null, ?array $enum = null): array
    {
        return self::with(['type' => 'string', 'enum' => $enum], $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function integer(?string $description = null): array
    {
        return self::with(['type' => 'integer'], $description);
    }

    /**
     * @return array<string, mixed>
     */
    public static function boolean(?string $description = null): array
    {
        return self::with(['type' => 'boolean'], $description);
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    public static function list(array $items, ?string $description = null): array
    {
        return self::with(['type' => 'array', 'items' => $items], $description);
    }

    /**
     * An object. A property that is required is named in $required; without properties it is an open object.
     *
     * @param  array<string, array<string, mixed>>|null  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    public static function object(?array $properties = null, ?string $description = null, array $required = []): array
    {
        return self::with(['type' => 'object', 'properties' => $properties, 'required' => $required === [] ? null : $required], $description);
    }

    /**
     * The whole input of a tool: an object with these properties; the names in $required must be given.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    public static function input(array $properties = [], array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties] + ($required === [] ? [] : ['required' => $required]);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function with(array $schema, ?string $description): array
    {
        if ($description !== null) {
            $schema['description'] = $description;
        }

        return array_filter($schema, fn (mixed $value): bool => $value !== null);
    }
}
