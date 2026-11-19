<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentAccessService;
use App\Support\DocumentSearch;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.documents and Query.document (docs/dokumentenverwaltung.md §8.1). Only what the viewer may read
 * (DocumentAccessService::visibleTo). The list does not load the content; the project with its members and the
 * viewer's own shares are read once for the whole list, so the number of queries does not grow with the rows.
 */
class DocumentQueries
{
    use ResolvesViewer;

    public function __construct(private readonly DocumentAccessService $access) {}

    /**
     * @param  array{projectId: int|string, kind?: string|null, search?: string|null}  $args
     * @return Collection<int, Document>
     */
    public function list(mixed $root, array $args, GraphQLContext $context): Collection
    {
        $viewer = $this->viewer($context);

        $query = Document::query()
            ->select(['documents.id', 'documents.project_id', 'documents.owner_id', 'documents.title', 'documents.kind', 'documents.project_access', 'documents.revision', 'documents.updated_by', 'documents.created_at', 'documents.updated_at', 'documents.deletion_id'])
            ->visibleTo($viewer)
            ->where('documents.project_id', $args['projectId'])
            ->with('project.members')
            ->orderByDesc('documents.updated_at')
            ->orderByDesc('documents.id');

        if (isset($args['kind'])) {
            $query->where('documents.kind', $args['kind']);
        }

        if (isset($args['search']) && trim($args['search']) !== '') {
            DocumentSearch::apply($query, $args['search']);
        }

        $documents = $query->get();
        $this->access->preload($viewer, $documents);

        return $documents;
    }

    /**
     * One version with its content, or null when the document does not exist or may not be read.
     *
     * @param  array{documentId: int|string, revision: int}  $args
     */
    public function version(mixed $root, array $args, GraphQLContext $context): ?DocumentVersion
    {
        $document = $this->one($root, ['id' => $args['documentId']], $context);

        return $document?->versions()->where('revision', $args['revision'])->first();
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function one(mixed $root, array $args, GraphQLContext $context): ?Document
    {
        $viewer = $this->viewer($context);

        $document = Document::query()->visibleTo($viewer)->with('project.members')->find($args['id']);

        if ($document !== null) {
            $this->access->preload($viewer, [$document]);
        }

        return $document;
    }
}
