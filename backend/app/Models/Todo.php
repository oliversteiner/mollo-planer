<?php

namespace App\Models;

use App\Models\Contracts\BelongsToProject;
use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status is computed by the status observer, never set from input.
 */
#[Fillable(['milestone_id', 'title', 'description', 'icon', 'due_date'])]
class Todo extends Model implements BelongsToProject
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'array',
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
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function owningProject(): Project
    {
        return $this->milestone->owningProject();
    }
}
