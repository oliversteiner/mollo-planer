<?php

namespace App\Planer;

/**
 * What an import did, or, in a dry run, would do. One list serves the
 * preview, the messages of a failed push and later the assistant.
 */
final class ImportResult
{
    /**
     * @param  list<array{kind: string, path: string, id: ?string, fields: list<string>}>  $changes  kind: create, update, move or delete
     * @param  list<array{path: string, kind: string, message: string}>  $errors  Nothing was written when there are any
     * @param  array<string, string>  $idMap  "-new-" ids of the file => the ids the app gave them
     * @param  array<string, mixed>|null  $document  The export after the import; null in a dry run and on errors
     */
    public function __construct(
        public readonly array $changes = [],
        public readonly array $errors = [],
        public readonly array $idMap = [],
        public readonly ?array $document = null,
        public readonly bool $dryRun = false,
        public readonly int|string|null $projectId = null,
    ) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'dryRun' => $this->dryRun,
            'projectId' => $this->projectId === null ? null : (string) $this->projectId,
            'changes' => $this->changes,
            'errors' => $this->errors,
            'idMap' => (object) $this->idMap,
            'document' => $this->document,
        ];
    }
}
