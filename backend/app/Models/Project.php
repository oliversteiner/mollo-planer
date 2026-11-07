<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\ProjectRole;
use App\Models\Concerns\VisibleToViewer;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'locale'])]
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
        ];
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
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Milestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    /**
     * Role of the user in this project, null if not a member.
     *
     * Uses the loaded members relation if present, otherwise one query.
     * This only looks up the role; what it allows is decided by
     * App\Support\ProjectPermissions.
     */
    public function roleOf(User $user): ?ProjectRole
    {
        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->getKey())
            : $this->members()->whereKey($user->getKey())->first();

        return $member?->membership->role;
    }

    protected static function projectPath(): ?string
    {
        return null;
    }
}
