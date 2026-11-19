<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One saved version of a document (docs/dokumentenverwaltung.md §3). Versions are never changed and never deleted
 * one by one: only the deletion of the document for good (database cascade) removes them. Written by the service
 * that saves documents, in the same transaction as the change.
 */
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'revision' => 'integer',
            'contributors' => 'array',
            'restored_from' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A document version is never changed.'));
        static::deleting(fn () => throw new LogicException('A document version is never deleted one by one.'));
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
