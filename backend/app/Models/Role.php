<?php

namespace App\Models;

use App\Enums\Right;
use App\Support\Rights;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A role: a named set of functions given to a person (scope system) or to a
 * membership (scope project). See docs/rollen-funktionen-rechte.md.
 *
 * Roles are few and read on almost every request, so all of them are read
 * once (registry) and found by id or key from there. What a role may do is
 * answered only by App\Support\Rights.
 *
 * @property int $id
 * @property string $scope
 * @property string $key
 * @property array{de: string, en: string} $names
 * @property list<string>|null $project_cap
 * @property bool $is_default
 */
#[Fillable(['scope', 'key', 'names', 'project_cap', 'is_default'])]
class Role extends Model
{
    public const SCOPE_SYSTEM = Right::SCOPE_SYSTEM;

    public const SCOPE_PROJECT = Right::SCOPE_PROJECT;

    /** @var array<int, self>|null */
    private static ?array $registry = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'names' => 'array',
            'project_cap' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * All roles by id, read once per request (cleared by Rights::clearCache).
     *
     * @return array<int, self>
     */
    public static function registry(): array
    {
        return self::$registry ??= self::query()->orderBy('id')->get()->keyBy('id')->all();
    }

    public static function forgetRegistry(): void
    {
        self::$registry = null;
    }

    public static function cached(int|string|null $id): ?self
    {
        return $id === null ? null : (self::registry()[(int) $id] ?? null);
    }

    /**
     * @return list<self>
     */
    public static function ofScope(string $scope): array
    {
        return array_values(array_filter(self::registry(), fn (self $role): bool => $role->scope === $scope));
    }

    public static function byKey(string $scope, string $key): ?self
    {
        foreach (self::registry() as $role) {
            if ($role->scope === $scope && $role->key === $key) {
                return $role;
            }
        }

        return null;
    }

    /** The role new people (system) or new members (project) get. */
    public static function defaultOf(string $scope): ?self
    {
        $roles = self::ofScope($scope);

        foreach ($roles as $role) {
            if ($role->is_default) {
                return $role;
            }
        }

        return $roles[0] ?? null;
    }

    /**
     * Find a role of the scope from what code or input hands over: the role
     * itself, its id, its key, or an enum of a shipped key. Null stays null.
     */
    public static function resolve(string $scope, self|BackedEnum|int|string|null $role): ?self
    {
        $found = match (true) {
            $role === null => null,
            $role instanceof self => $role,
            $role instanceof BackedEnum => self::byKey($scope, (string) $role->value),
            is_int($role) => self::cached($role),
            default => self::byKey($scope, $role),
        };

        return $found?->scope === $scope ? $found : null;
    }

    /**
     * Like resolve(), for input: an unknown role is a validation error.
     *
     * @throws ValidationException
     */
    public static function resolveOrFail(string $scope, self|BackedEnum|int|string|null $role): self
    {
        return self::resolve($scope, $role)
            ?? throw ValidationException::withMessages(['role' => __('validation.exists', ['attribute' => 'role'])]);
    }

    /** Id of a role of the scope, for seeders, factories and tests. */
    public static function idOf(string $scope, self|BackedEnum|int|string $role): int
    {
        return self::resolveOrFail($scope, $role)->id;
    }

    /** Shipped with the app (config/permissions.php), not made in the settings. */
    public function getIsStandardAttribute(): bool
    {
        return array_key_exists($this->key, (array) config("permissions.roles.{$this->scope}"));
    }

    /**
     * The rights the role grants, in the order of the enum.
     *
     * @return list<Right>
     */
    public function getRightsAttribute(): array
    {
        return Rights::rightsOfRole($this);
    }

    public function name(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->names[$locale] ?? $this->names['de'] ?? $this->key;
    }
}
