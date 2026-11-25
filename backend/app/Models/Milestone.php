<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\ItemStatus;
use App\Enums\ReleaseStatus;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\VisibleToViewer;
use App\Models\Contracts\BelongsToProject;
use App\Observers\MilestoneObserver;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Status is computed by the status observer, never set from input.
 *
 * @property array<string, mixed>|null $description
 * @property ItemStatus $status
 * @property int|null $price_cents fixed price in Rappen (Auftrag 39), only through setMilestonePrice
 */
#[Fillable(['project_id', 'title', 'description', 'icon', 'due_date', 'position', 'release_version', 'release_status', 'release_date'])]
#[ObservedBy(MilestoneObserver::class)]
class Milestone extends Model implements BelongsToProject
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory, RecordsCreator, SoftDeletes, VisibleToViewer;

    /**
     * Same default as the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * The release as the API shows it: version, status and the date of the
     * release (JJJJ-MM-TT, null while there is none), or null when the
     * milestone has no version.
     *
     * @return array{version: string, status: string, date: string|null}|null
     */
    public function getReleaseAttribute(): ?array
    {
        if ($this->release_version === null) {
            return null;
        }

        return ['version' => $this->release_version, 'status' => $this->release_status?->value, 'date' => $this->getAttribute('release_date')?->format('Y-m-d')];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => RichTextCast::class,
            'status' => ItemStatus::class,
            'due_date' => 'date',
            'release_status' => ReleaseStatus::class,
            'release_date' => 'date',
            'price_cents' => 'integer',
        ];
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class)->orderBy('position')->orderBy('id');
    }

    public function owningProject(): Project
    {
        return $this->project;
    }

    protected static function projectPath(): ?string
    {
        return 'project';
    }
}
