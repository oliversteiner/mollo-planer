<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Contracts\BelongsToProject;
use App\Observers\CommentObserver;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The author (user_id) is set from the logged-in user, never from input.
 *
 * @property array<string, mixed>|null $body
 */
#[Fillable(['body'])]
#[ObservedBy(CommentObserver::class)]
class Comment extends Model implements BelongsToProject
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => RichTextCast::class,
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
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
