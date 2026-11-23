<?php

namespace App\Models;

use App\Enums\DocumentAccess;
use App\Enums\DocumentKind;
use App\Services\DocumentAccessService;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * A document of a project (docs/dokumentenverwaltung.md): Markdown in the canonical form of the profile
 * "Dokument", owner, a level for all project members and shares per person. The owner and updated_by come from
 * the login, never from input; project_id never changes. Every save writes a DocumentVersion.
 */
/**
 * @property DocumentKind $kind
 * @property DocumentAccess $project_access
 * @property int $revision
 * @property int $project_id
 * @property int|null $owner_id
 * @property string $title
 * @property string $content
 */
#[Fillable(['title', 'kind', 'content', 'project_access'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Same defaults as the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'other',
        'project_access' => 'read',
        'revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'project_access' => DocumentAccess::class,
            'revision' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // A document never moves to another project (§4.3 rule 4).
        static::updating(function (self $document): void {
            if ($document->isDirty('project_id')) {
                throw new LogicException('The project of a document never changes.');
            }
        });
    }

    /**
     * Only the documents the viewer may read, without those in the trash (DocumentAccessService::visibleTo).
     *
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleToViewer(Builder $query): void
    {
        $user = Auth::user();

        $this->scopeVisibleTo($query, $user instanceof User ? $user : null);
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        app(DocumentAccessService::class)->visibleTo($query, $user);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<DocumentShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    /**
     * Newest first.
     *
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('revision');
    }
}
