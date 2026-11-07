<?php

namespace App\Models;

use App\Models\Contracts\BelongsToProject;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The author (user_id) is set from the logged-in user, never from input.
 */
#[Fillable(['body'])]
class Comment extends Model implements BelongsToProject
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Null when the author has been deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->user_id !== null && (int) $this->user_id === (int) $user->getKey();
    }

    public function owningProject(): Project
    {
        return $this->task->owningProject();
    }
}
