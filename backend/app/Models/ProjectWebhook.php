<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The webhook of a project: where the app announces a new revision of the
 * planer file. The secret (token or HMAC secret) is encrypted in the database
 * and hidden from every array and JSON form; nothing reads it back to a person.
 */
#[Fillable(['kind', 'target', 'secret'])]
#[Hidden(['secret'])]
class ProjectWebhook extends Model
{
    public const GITHUB = 'github';

    public const GENERIC = 'generic';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_sent_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'last_sent_revision' => 'integer',
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
     * Whether a secret is stored (GraphQL: hasSecret). The value is never an output.
     *
     * @return Attribute<bool, never>
     */
    protected function hasSecret(): Attribute
    {
        return Attribute::get(fn (): bool => ($this->attributes['secret'] ?? '') !== '');
    }
}
