<?php

namespace App\Models\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * The attribute "role" on top of the column role_id: reading gives the Role
 * (from the registry, no query), writing takes a role, its key, its id or an
 * enum of a shipped key. Used by User (system roles) and ProjectMember
 * (project roles).
 */
trait HasRole
{
    abstract protected static function roleScope(): string;

    /**
     * @return Attribute<Role|null, mixed>
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?Role => Role::cached($attributes['role_id'] ?? null),
            set: fn (mixed $value): array => [
                'role_id' => $value === null ? null : Role::resolveOrFail(static::roleScope(), $value)->id,
            ],
        )->withoutObjectCaching(); // otherwise an enum or key that was set would be read back as is
    }
}
