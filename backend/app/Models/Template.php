<?php

namespace App\Models;

use App\Planer\TemplateContent;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A template for projects, app-wide (docs/planer-abgleich.md 3.4, 3.8): a
 * planer-v2 document with fixed values in `content`, with the labels and
 * optionally the outline of milestones, todos and tasks. Built-in ones have
 * a key and cannot be changed; to adapt one it is duplicated. A project made
 * from a template has copies, so changing or deleting the template changes
 * no project.
 */
#[Fillable(['name', 'description', 'locale', 'content'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function isBuiltin(): bool
    {
        return $this->key !== null;
    }

    /**
     * Shown name: translated for built-in templates.
     */
    public function displayName(): string
    {
        return $this->isBuiltin() ? self::translated("labels.templates.{$this->key}.name", $this->name) : $this->name;
    }

    public function displayDescription(): ?string
    {
        return $this->isBuiltin() ? self::translated("labels.templates.{$this->key}.description", $this->description) : $this->description;
    }

    private static function translated(string $key, ?string $fallback): ?string
    {
        $text = __($key);

        return $text === $key ? $fallback : $text;
    }

    /**
     * The labels of the template, in order.
     *
     * @return list<array{key: string, names: array<string, string>, category: ?string, color: string}>
     */
    public function labels(): array
    {
        return TemplateContent::labels($this->content ?? []);
    }

    /**
     * Milestones, todos and tasks by title, for looking at.
     *
     * @return list<array<string, mixed>>
     */
    public function outline(): array
    {
        return TemplateContent::outline($this->content ?? []);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
