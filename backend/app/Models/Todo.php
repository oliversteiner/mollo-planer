<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\ItemStatus;
use App\Models\Concerns\DerivesProject;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\VisibleToViewer;
use App\Models\Contracts\BelongsToProject;
use App\Observers\TodoObserver;
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
#[Fillable(['milestone_id', 'title', 'description', 'icon', 'due_date'])]
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
        ];
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
        return $this->milestone->project_id;
    }

    protected static function projectPath(): ?string
    {
        return 'project';
    }
}
