<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\ProjectMembership;
use App\Support\AppSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a project (checked by @can: projects.create). Whoever creates it
 * becomes a member with the creator role of the settings (standard: owner),
 * admins too: every project keeps a member who may delete it
 * (docs/rollen-funktionen-rechte.md §7.3, docs/fix-m1-abnahme.md F3.3).
 *
 * Further members come in the same transaction, each through
 * ProjectMembership, so the ground rules hold (docs/fix-m1-mitglieder.md §1.2).
 */
class CreateProject
{
    public function __construct(private readonly ProjectMembership $membership) {}

    /**
     * @param  array{name: string, description?: array<string, mixed>|null, icon?: string|null, locale?: string|null, members?: list<array{userId: int|string, role: string}>|null}  $args
     *
     * @throws ValidationException
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

            if ($creator instanceof User) {
                $project->members()->attach($creator->getKey(), ['role_id' => AppSettings::projectCreatorRole()->id]);
                $this->addMembers($project, $creator, $args['members'] ?? []);
            }

            return $project;
        });
    }

    /**
     * @param  list<array{userId: int|string, role: string}>  $members
     *
     * @throws ValidationException
     */
    private function addMembers(Project $project, User $creator, array $members): void
    {
        $seen = [];

        foreach (array_values($members) as $index => $entry) {
            $userId = (string) $entry['userId'];

            // The creator keeps the creator role.
            if ($userId === (string) $creator->getKey()) {
                continue;
            }

            if (isset($seen[$userId])) {
                throw ValidationException::withMessages(["input.members.{$index}.userId" => __('projects.member_twice')]);
            }
            $seen[$userId] = true;

            $user = User::query()->whereKey($userId)->whereNotNull('approved_at')->first()
                ?? throw ValidationException::withMessages(["input.members.{$index}.userId" => __('projects.member_unknown')]);

            $role = Role::resolve(Role::SCOPE_PROJECT, $entry['role'])
                ?? throw ValidationException::withMessages(["input.members.{$index}.role" => __('validation.exists', ['attribute' => 'role'])]);

            try {
                $this->membership->set($creator, $project, $user, $role);
            } catch (AuthorizationException) {
                throw ValidationException::withMessages(["input.members.{$index}.role" => __('projects.member_role_refused')]);
            }
        }
    }
}
