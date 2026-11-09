<?php

namespace App\Models;

use Database\Factories\LabelSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of labels, app-wide. Built-in sets have a key and cannot
 * be changed; own sets have a creator.
 */
#[Fillable(['name', 'description'])]
class LabelSet extends Model
{
    /** @use HasFactory<LabelSetFactory> */
    use HasFactory;

    public function isBuiltin(): bool
    {
        return $this->key !== null;
    }

    /**
     * Shown name: translated for built-in sets.
     */
    public function displayName(): string
    {
        return $this->isBuiltin() ? __("labels.sets.{$this->key}.name") : $this->name;
    }

    public function displayDescription(): ?string
    {
        return $this->isBuiltin() ? __("labels.sets.{$this->key}.description") : $this->description;
    }

    /**
     * @return HasMany<Label, $this>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
