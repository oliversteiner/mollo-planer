<?php

namespace App\GraphQL\Errors;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use RuntimeException;

/**
 * Somebody saved the document since the editor loaded it (docs/dokumentenverwaltung.md §6). The error carries the
 * code DOCUMENT_CONFLICT and the current revision; nothing was saved. The editor offers: reload, or save one's own
 * version as a new document.
 */
final class DocumentConflict extends RuntimeException implements ClientAware, ProvidesExtensions
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct(__('documents.conflict', ['revision' => $currentRevision]));
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'document';
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return ['code' => 'DOCUMENT_CONFLICT', 'currentRevision' => $this->currentRevision];
    }
}
