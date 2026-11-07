<?php

namespace App\Models;

use App\Models\Contracts\BelongsToProject;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status and position are changed by moveTask, the assignee by assignTask.
 */
#[Fillable(['todo_id', 'title', 'description', 'icon', 'due_date'])]
class Task extends Model implements BelongsToProject
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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
}
