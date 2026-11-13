<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Enums\TaskStatus;
use App\Models\Concerns\DerivesProject;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\VisibleToViewer;
use App\Models\Contracts\BelongsToProject;
use App\Observers\TaskObserver;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * Status and position are changed by moveTask, the assignee by assignTask.
 */
#[Fillable(['todo_id', 'title', 'description', 'icon', 'due_date'])]
#[ObservedBy(TaskObserver::class)]
class Task extends Model implements BelongsToProject
{
    /** @use HasFactory<TaskFactory> */
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
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /**
     * Labels of the task, in the order of their set.
     *
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->orderBy('labels.position')->orderBy('labels.id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Comments, oldest first.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest('id');
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

    public function owningProject(): Project
    {
        return $this->project;
    }

    protected function parentProjectId(): int|string
    {
        return Todo::query()->whereKey($this->todo_id)->value('project_id');
    }

    /**
     * All tasks of a project (through todo and milestone).
     *
     * @param  Builder<static>  $query
     */
    public function scopeInProject(Builder $query, int|string $projectId): void
    {
        $query->where('project_id', $projectId);
    }

    /**
     * Only tasks assigned to the logged-in user (myTasks). Combine with
     * visibleToViewer so tasks in projects the user left stay hidden.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignedToViewer(Builder $query): void
    {
        $user = Auth::user();

        $this->scopeAssignedTo($query, $user instanceof User ? $user : null);
    }

    /**
     * Only tasks assigned to the given user. Guests get none.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignedTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('assigned_user_id', $user->getKey());
    }

    protected static function projectPath(): ?string
    {
        return 'project';
    }
}
