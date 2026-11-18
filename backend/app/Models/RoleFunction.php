<?php

namespace App\Models;

use App\Enums\Right;
use App\Support\Rights;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A function: a named set of rights of one scope. Roles are made of
 * functions. "Function" is reserved in PHP, hence the name. See
 * docs/rollen-funktionen-rechte.md.
 *
 * @property int $id
 * @property string $scope
 * @property string $key
 * @property array{de: string, en: string} $names
 */
#[Fillable(['scope', 'key', 'names'])]
class RoleFunction extends Model
{
    protected $table = 'functions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'names' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_functions', 'function_id', 'role_id')->orderBy('roles.id');
    }

    /** Shipped with the app (config/permissions.php), not made in the settings. */
    public function getIsStandardAttribute(): bool
    {
        return array_key_exists($this->key, (array) config("permissions.functions.{$this->scope}"));
    }

    /**
     * The rights of the function, in the order of the enum.
     *
     * @return list<Right>
     */
    public function getRightsAttribute(): array
    {
        return Rights::rightsOfFunction($this);
    }

    /** How many roles use the function. A function in use is not deleted. */
    public function getUsageCountAttribute(): int
    {
        return $this->roles()->count();
    }
}
