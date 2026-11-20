<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The search in titles and content of documents. MariaDB: the FULLTEXT index (boolean mode, every word with
 * a prefix) and, for the short words the index ignores, a search in the title. Elsewhere (the tests on SQLite): LIKE
 * on title and content, every word must occur.
 */
final class DocumentSearch
{
    /**
     * @param  Builder<Document>  $query
     */
    public static function apply(Builder $query, string $term): void
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $boolean = implode(' ', array_map(static fn (string $word): string => '+'.$word.'*', $words));

            $query->where(function (Builder $search) use ($boolean, $words): void {
                $search->whereRaw('MATCH(documents.title, documents.content) AGAINST (? IN BOOLEAN MODE)', [$boolean]);

                $search->orWhere(function (Builder $title) use ($words): void {
                    foreach ($words as $word) {
                        $title->where('documents.title', 'like', self::like($word));
                    }
                });
            });

            return;
        }

        foreach ($words as $word) {
            $query->where(fn (Builder $any) => $any
                ->where('documents.title', 'like', self::like($word))
                ->orWhere('documents.content', 'like', self::like($word)));
        }
    }

    private static function like(string $word): string
    {
        return '%'.addcslashes($word, '%_\\').'%';
    }
}
