<?php

namespace App\Rules;

use App\Enums\Right;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * A change of a person's system role (the value is the new role key) or the
 * deletion of the person (deleting: true) must not leave a project without a
 * member who may delete it. Holds for admins too (§7.3,
 * docs/fix-m1-abnahme.md F3.4). The message names the projects.
 */
class KeepsProjectHolders implements ValidationRule
{
    public function __construct(
        private readonly ?User $person,
        private readonly bool $deleting = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->person === null) {
            return;
        }

        $role = $this->deleting ? null : Role::resolve(Role::SCOPE_SYSTEM, (string) $value);

        // An unknown role is refused elsewhere.
        if (! $this->deleting && $role === null) {
            return;
        }

        $projects = array_map(fn (Project $project): string => $project->name, Rights::projectsOnlyHeldBy($this->person, $role));

        if ($projects !== []) {
            $fail(__('projects.only_holder', [
                'name' => $this->person->name,
                'projects' => Arr::join($projects, ', ', __('projects.list_and')),
                'right' => Right::PROJECT_DELETE->label(),
            ]));
        }
    }
}
