<?php

namespace App\Models;

use App\Casts\RichTextCast;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description', 'icon', 'locale'])]
#[ObservedBy(ProjectObserver::class)]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes, VisibleToViewer;

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
            'description' => RichTextCast::class,
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
     * Projects that are not archived (dashboard).
     *
     * @param  Builder<static>  $query
     */
    public function scopeNotArchived(Builder $query): void
    {
        $this->scopeArchived($query, false);
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
     * Background knowledge for the assistant (docs/auftrag-m6.md §3).
     *
     * @return HasOne<ProjectAiContext, $this>
     */
    public function aiContext(): HasOne
    {
        return $this->hasOne(ProjectAiContext::class);
    }

    /**
     * Where the app announces a new revision of the planer file (docs/auftrag-m5.md §4).
     *
     * @return HasOne<ProjectWebhook, $this>
     */
    public function webhook(): HasOne
    {
        return $this->hasOne(ProjectWebhook::class);
    }

    /**
     * The labels of this project, in order.
     *
     * @return HasMany<Label, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('position')->orderBy('id')->chaperone('project');
    }

    /**
     * The template the project was made from (only to show where it came from).
     *
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
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
