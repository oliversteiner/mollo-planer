<?php

namespace App\GraphQL\Resolvers;

use App\Enums\DocumentAccess;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Services\DocumentAccessService;
use App\Support\DocumentContent;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * The fields of Document that need more than a column: the content (the list does not load it, so it is read on
 * demand), the content as TipTap JSON, the viewer's access and the shares (only for those who manage the document).
 */
class DocumentFields
{
    use ResolvesViewer;

    public function __construct(private readonly DocumentAccessService $access) {}

    public function content(Document $document): string
    {
        return $this->markdown($document);
    }

    /**
     * @return array<string, mixed>
     */
    public function contentJson(Document $document): array
    {
        return DocumentContent::jsonFromMarkdown($this->markdown($document));
    }

    public function viewerAccess(Document $document, mixed $args, GraphQLContext $context): DocumentAccess
    {
        return $this->access->levelOf($this->viewer($context), $document);
    }

    /**
     * @return Collection<int, DocumentShare>
     */
    public function shares(Document $document, mixed $args, GraphQLContext $context): Collection
    {
        if ($this->access->levelOf($this->viewer($context), $document) !== DocumentAccess::MANAGE) {
            return new Collection;
        }

        return $document->shares()->with('user')->get();
    }

    private function markdown(Document $document): string
    {
        if (! array_key_exists('content', $document->getAttributes())) {
            $document->setAttribute('content', (string) Document::withTrashed()->whereKey($document->getKey())->value('content'));
        }

        return $document->content;
    }
}
