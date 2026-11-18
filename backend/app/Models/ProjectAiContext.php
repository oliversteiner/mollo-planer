<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Background knowledge of a project for the assistant (docs/auftrag-m6.md §3): a text and the text of one file.
 */
class ProjectAiContext extends Model
{
    protected $guarded = ['id', 'project_id', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shared_with_tools' => 'boolean'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Text and file together, as the assistant gets them. */
    public function combined(): string
    {
        return trim(implode("\n\n", array_filter([(string) $this->text, (string) $this->file_text], fn (string $part): bool => trim($part) !== '')));
    }

    /**
     * Characters of text and file together (GraphQL: characters).
     *
     * @return Attribute<int, never>
     */
    protected function characters(): Attribute
    {
        return Attribute::get(fn (): int => mb_strlen((string) $this->text) + mb_strlen((string) $this->file_text));
    }
}
