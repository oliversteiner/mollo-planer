<?php

namespace App\Models;

use App\Enums\LabelColor;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label of a set, for tasks of the projects that use the set.
 */
#[Fillable(['name', 'category', 'color', 'position'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => LabelColor::class,
            'position' => 'integer',
        ];
    }

    /**
     * Shown name: translated for labels of built-in sets.
     */
    public function displayName(): string
    {
        return $this->key !== null ? __("labels.labels.{$this->key}") : $this->name;
    }

    /**
     * Shown category: translated for labels of built-in sets.
     */
    public function displayCategory(): ?string
    {
        return $this->key !== null && $this->category !== null ? __("labels.categories.{$this->category}") : $this->category;
    }

    /**
     * @return BelongsTo<LabelSet, $this>
     */
    public function labelSet(): BelongsTo
    {
        return $this->belongsTo(LabelSet::class);
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
