<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\ItemStatus;
use App\Models\Concerns\DerivesProject;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\VisibleToViewer;
use App\Models\Contracts\BelongsToProject;
use App\Observers\TodoObserver;
use App\Services\Ordering;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status is computed by the status observer, never set from input.
 */
#[Fillable(['milestone_id', 'title', 'description', 'icon', 'due_date', 'position'])]
#[ObservedBy(TodoObserver::class)]
class Todo extends Model implements BelongsToProject
{
    /** @use HasFactory<TodoFactory> */
    use DerivesProject, HasFactory, RecordsCreator, VisibleToViewer;

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
            'position' => 'integer',
        ];
    }

    /**
     * A new todo goes to the end of its milestone, or to the given position;
     * the todos from there on move one place back. A deleted todo leaves no gap.
     */
    protected static function booted(): void
    {
        static::creating(function (Todo $todo): void {
            $ordering = app(Ordering::class);
            $group = self::query()->where('milestone_id', $todo->milestone_id);

            if ($todo->position === null) {
                $todo->position = $ordering->nextPosition($todo->project_id, $group);

                return;
            }

            $ordering->openSlot($todo->project_id, $group, $todo->position);
        });

        static::deleted(function (Todo $todo): void {
            app(Ordering::class)->close(
                $todo->project_id,
                self::query()->where('milestone_id', $todo->milestone_id),
            );
        });
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function owningProject(): Project
    {
        return $this->project;
    }

    protected function parentProjectId(): int|string
    {
        return Milestone::query()->whereKey($this->milestone_id)->value('project_id');
    }

    protected static function projectPath(): ?string
    {
        return 'project';
    }
}
