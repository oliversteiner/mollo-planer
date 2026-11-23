<?php

namespace App\GraphQL\Resolvers;

use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The fields of DocumentVersion that need more than a column: the content (the list of versions does not load it)
 * and the people who contributed (docs/dokumentenverwaltung.md §3: in M7 only the person who saved; M8 fills the
 * column).
 */
class DocumentVersionFields
{
    public function content(DocumentVersion $version): string
    {
        if (! array_key_exists('content', $version->getAttributes())) {
            $version->setAttribute('content', (string) DocumentVersion::query()->whereKey($version->getKey())->value('content'));
        }

        return $version->content;
    }

    /**
     * @return Collection<int, User>
     */
    public function contributors(DocumentVersion $version): Collection
    {
        $ids = $version->contributors ?? array_filter([$version->created_by]);

        return $ids === [] ? new Collection : User::query()->whereKey($ids)->get();
    }
}
