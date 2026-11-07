<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership of a user in a project (table project_user).
 *
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $role
 */
class ProjectMember extends Pivot
{
    protected $table = 'project_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }
}
