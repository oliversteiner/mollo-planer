<?php

namespace App\Models;

use App\Models\Concerns\HasRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership of a user in a project (table project_user).
 *
 * @property int $project_id
 * @property int $user_id
 * @property int|null $role_id
 * @property Role|null $role
 */
class ProjectMember extends Pivot
{
    use HasRole;

    protected $table = 'project_user';

    public $incrementing = true;

    protected static function roleScope(): string
    {
        return Role::SCOPE_PROJECT;
    }
}
