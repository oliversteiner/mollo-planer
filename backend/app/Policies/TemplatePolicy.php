<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Template;
use App\Models\User;
use App\Support\Rights;

/**
 * Templates are app-wide and everybody signed in sees them (reading is the
 * scope, not a policy). Creating and duplicating needs templates.manage.
 * Own templates are changed and deleted by their creator (with that right) or
 * by anyone with templates.manageAny; built-in ones by nobody.
 */
class TemplatePolicy
{
    public function create(User $user): bool
    {
        return Rights::allows($user, Right::TEMPLATES_MANAGE);
    }

    public function duplicate(User $user, Template $template): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Template $template): bool
    {
        return ! $template->isBuiltin()
            && (Rights::allows($user, Right::TEMPLATES_MANAGE_ANY)
                || ((int) $template->created_by === (int) $user->getKey() && $this->create($user)));
    }

    public function delete(User $user, Template $template): bool
    {
        return $this->update($user, $template);
    }
}
