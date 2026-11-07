<?php

namespace App\Models;

use App\Models\Contracts\BelongsToProject;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status is computed by the status observer, never set from input.
 */
#[Fillable(['project_id', 'title', 'description', 'icon', 'due_date'])]
class Milestone extends Model implements BelongsToProject
{
    /** @use HasFactory<MilestoneFactory> */
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
}
