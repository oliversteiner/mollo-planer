<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Enums\Right;
use App\Models\Project;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Rights;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project (checked by @can: projects.create). Someone who may
 * manage any project does not become a member. Everyone else who creates a
 * project becomes a member with the creator role of the settings (standard:
 * owner), otherwise they could not see it.
 */
class CreateProject
{
    /**
     * @param  array{name: string, description?: string|null, icon?: string|null, locale?: string|null}  $args
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $creator = Auth::user();

        return DB::transaction(function () use ($args, $creator): Project {
            $project = Project::create([
                'name' => $args['name'],
                'description' => $args['description'] ?? null,
                'icon' => $args['icon'] ?? null,
                'locale' => $args['locale'] ?? Locale::DE->value,
            ]);

            if ($creator instanceof User && ! Rights::allows($creator, Right::PROJECTS_MANAGE_ANY)) {
                $project->members()->attach($creator->getKey(), ['role_id' => AppSettings::projectCreatorRole()->id]);
            }

            return $project;
        });
    }
}
