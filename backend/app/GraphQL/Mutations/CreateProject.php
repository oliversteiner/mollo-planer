<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project (admins only, checked by @can). The admin does not
 * become a member; owners are added with setProjectMember.
 */
class CreateProject
{
    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, locale?: string|null}  $args
     */
    public function __invoke(mixed $root, array $args): Project
    {
        return DB::transaction(fn (): Project => Project::create([
            'name' => $args['name'],
            'description' => $args['description'] ?? null,
            'icon' => $args['icon'] ?? null,
            'locale' => $args['locale'] ?? Locale::DE->value,
        ]));
    }
}
