<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Document;
use App\Models\Project;
use App\Services\Deletion;
use App\Services\DocumentPdf;
use App\Services\DocumentService;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * createDocument, updateDocument, restoreDocumentVersion, setDocumentSharing, transferDocument, deleteDocument, exportDocumentPdf
 * (docs/dokumentenverwaltung.md §8.1). The rights are checked by @can (DocumentPolicy, ProjectPolicy::createDocument);
 * owner and updated_by come from the login.
 */
class DocumentMutations
{
    use ResolvesViewer;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly Deletion $deletion,
        private readonly DocumentPdf $pdf,
    ) {}

    /**
     * @param  array{input: array{projectId: int|string, title: string, kind?: string|null, contentJson: array<string, mixed>, projectAccess?: string|null}}  $args
     *
     * @throws ValidationException
     */
    public function create(mixed $root, array $args, GraphQLContext $context): Document
    {
        return $this->documents->create($this->viewer($context), Project::query()->findOrFail($args['input']['projectId']), $args['input']);
    }

    /**
     * @param  array{id: int|string, revision: int, input: array<string, mixed>}  $args
     */
    public function update(mixed $root, array $args, GraphQLContext $context): Document
    {
        return $this->documents->update($this->viewer($context), Document::query()->findOrFail($args['id']), $args['revision'], $args['input']);
    }

    /**
     * The PDF of a document (reading is enough; an archived project allows it).
     *
     * @param  array{id: int|string}  $args
     * @return array{fileName: string, mimeType: string, contentBase64: string}
     */
    public function exportPdf(mixed $root, array $args): array
    {
        $document = Document::query()->with(['project', 'owner'])->findOrFail($args['id']);

        return [
            'fileName' => $this->pdf->fileName($document),
            'mimeType' => 'application/pdf',
            'contentBase64' => base64_encode($this->pdf->render($document)),
        ];
    }

    /**
     * @param  array{id: int|string, revision: int}  $args
     *
     * @throws ValidationException
     */
    public function restoreVersion(mixed $root, array $args, GraphQLContext $context): Document
    {
        return $this->documents->restoreVersion($this->viewer($context), Document::query()->findOrFail($args['id']), $args['revision']);
    }

    /**
     * @param  array{id: int|string, input: array<string, mixed>}  $args
     */
    public function setSharing(mixed $root, array $args): Document
    {
        return $this->documents->setSharing(Document::query()->findOrFail($args['id']), $args['input']);
    }

    /**
     * @param  array{id: int|string, ownerId: int|string}  $args
     */
    public function transfer(mixed $root, array $args): Document
    {
        return $this->documents->transfer(Document::query()->findOrFail($args['id']), $args['ownerId']);
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function delete(mixed $root, array $args): Document
    {
        /** @var Document $document */
        $document = $this->deletion->delete(Document::query()->findOrFail($args['id']));

        return $document;
    }
}
