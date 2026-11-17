<?php

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\VisibleToViewer;
use App\Observers\ProjectObserver;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'icon', 'locale'])]
#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, VisibleToViewer;

    /**
     * Same default as the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'de',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'archived_at' => 'datetime',
        ];
    }

    /** Archived: read only, except bringing it back and the trash (Rights). */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Archived projects, or (false) the others.
     *
     * @param  Builder<static>  $query
     */
    public function scopeArchived(Builder $query, bool $archived = true): void
    {
        $archived ? $query->whereNotNull('projects.archived_at') : $query->whereNull('projects.archived_at');
    }

    /**
     * Members of the project with their role (pivot attribute "membership").
     *
     * @return BelongsToMany<User, $this, ProjectMember, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectMember::class)
            ->as('membership')
            ->withPivot('id', 'role_id')
            ->withTimestamps();
    }

    /**
     * The label set the tasks of this project use (null: none).
     *
     * @return BelongsTo<LabelSet, $this>
     */
    public function labelSet(): BelongsTo
    {
        return $this->belongsTo(LabelSet::class);
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Role of the user in this project, null if not a member.
     *
     * Uses the loaded members relation if present, otherwise one query.
     * This only looks up the role; what it allows is decided by
     * App\Support\Rights.
     */
    public function roleOf(User $user): ?Role
    {
        return Role::cached($this->roleIdOf($user));
    }

    public function roleIdOf(User $user): ?int
    {
        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->getKey())
            : $this->members()->whereKey($user->getKey())->first();

        $roleId = $member?->membership->role_id;

        return $roleId === null ? null : (int) $roleId;
    }

    protected static function projectPath(): ?string
    {
        return null;
    }
}
