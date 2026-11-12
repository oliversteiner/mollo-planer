<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\ItemStatus;
use App\Models\Concerns\VisibleToViewer;
use App\Models\Contracts\BelongsToProject;
use App\Services\Ordering;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status is computed by the status observer, never set from input.
 */
#[Fillable(['project_id', 'title', 'description', 'icon', 'due_date', 'position'])]
class Milestone extends Model implements BelongsToProject
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory, VisibleToViewer;

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
            'status' => ItemStatus::class,
            'due_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Todo, $this>
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class);
    }

    public function owningProject(): Project
    {
        return $this->project;
    }

    protected static function projectPath(): ?string
    {
        return 'project';
    }

    /**
     * A new milestone goes to the end of its project, or to the given position;
     * the milestones from there on move one place back. A deleted milestone
     * leaves no gap.
     */
    protected static function booted(): void
    {
        static::creating(function (Milestone $milestone): void {
            $ordering = app(Ordering::class);
            $group = self::query()->where('project_id', $milestone->project_id);

            if ($milestone->position === null) {
                $milestone->position = $ordering->nextPosition($milestone->project_id, $group);

                return;
            }

            $ordering->openSlot($milestone->project_id, $group, $milestone->position);
        });

        static::deleted(function (Milestone $milestone): void {
            app(Ordering::class)->close(
                $milestone->project_id,
                self::query()->where('project_id', $milestone->project_id),
            );
        });
    }
}
