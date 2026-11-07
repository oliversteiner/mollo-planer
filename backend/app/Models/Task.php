<?php

namespace App\Models;

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
            'description' => 'array',
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

    public function owningProject(): Project
    {
        return $this->todo->owningProject();
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
        return 'todo.milestone.project';
    }
}
