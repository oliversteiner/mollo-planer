<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\IdeaState;
use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private idea (docs/auftrag-m6.md §2): title, description, optionally a project and a draft (a planer-v2
 * excerpt). Only its author changes it; with ideas.viewAny others may read it. The author (user_id) comes from
 * the login, never from input.
 *
 * @property array<string, mixed>|null $description
 * @property IdeaState $status
 */
#[Fillable(['title', 'description', 'draft'])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Same default as the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => RichTextCast::class,
            'status' => IdeaState::class,
            'adopted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * The todos made from this idea.
     *
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }
}
