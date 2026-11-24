<?php

namespace App\Tools\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * A "contains" search where % and _ in the search text mean themselves (Auftrag 42 E): a search for "100%" finds the names
 * with "100%", and "_" is no wildcard for any one character. "!" is the escape character, because it is one character in
 * MariaDB and in SQLite alike (a backslash is not).
 */
final class LikeSearch
{
    public static function pattern(string $search): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
    }

    /**
     * @param  Builder<*>  $query
     * @param  'and'|'or'  $boolean
     * @return Builder<*>
     */
    public static function where(Builder $query, string $column, string $search, string $boolean = 'and'): Builder
    {
        return $query->whereRaw($query->getQuery()->getGrammar()->wrap($column)." like ? escape '!'", [self::pattern($search)], $boolean);
    }
}
