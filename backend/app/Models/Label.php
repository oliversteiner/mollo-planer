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
 * A label of a project, for its tasks. The key is stable (a-z, 0-9, _ and -,
 * unique in the project) and never changes; the names are one per language.
 */
#[Fillable(['key', 'names', 'category', 'color', 'position'])]
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
            'names' => 'array',
            'color' => LabelColor::class,
            'position' => 'integer',
        ];
    }

    /**
     * The name in the language of the request, else in the language of the
     * project (when it is loaded), else German, English or any.
     */
    public function displayName(): string
    {
        $names = (array) $this->names;
        $projectLocale = $this->relationLoaded('project') ? $this->project?->locale->value : null;

        foreach ([app()->getLocale(), $projectLocale, 'de', 'en'] as $locale) {
            if ($locale !== null && ($names[$locale] ?? '') !== '') {
                return (string) $names[$locale];
            }
        }

        return (string) (array_values(array_filter($names, 'is_string'))[0] ?? $this->key);
    }

    /**
     * The category as written; for those of the built-in templates the translation.
     */
    public function displayCategory(): ?string
    {
        return self::translateCategory($this->category);
    }

    public static function translateCategory(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        $translated = __("labels.categories.{$category}");

        return $translated === "labels.categories.{$category}" ? $category : $translated;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
