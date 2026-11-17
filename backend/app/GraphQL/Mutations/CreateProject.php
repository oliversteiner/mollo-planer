<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Planer\TemplateScaffold;
use App\Services\ProjectLabels;
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
 *
 * With a template the project gets copies of its labels and its outline
 * (docs/auftrag-m3.md 3.3), in the same transaction: one change, revision 1.
 */
class CreateProject
{
    public function __construct(
        private readonly ProjectMembership $membership,
        private readonly ProjectLabels $labels,
        private readonly TemplateScaffold $scaffold,
    ) {}

    /**
     * @param  array{name: string, description?: array<string, mixed>|null, icon?: string|null, locale?: string|null, members?: list<array{userId: int|string, role: string}>|null, templateId?: int|string|null}  $args
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $root, array $args): Project
    {
        $creator = Auth::user();

        $project = DB::transaction(function () use ($args, $creator): Project {
            $template = isset($args['templateId'])
                ? Template::query()->find($args['templateId']) ?? throw ValidationException::withMessages(['input.templateId' => __('labels.template_unknown')])
                : null;

            $project = new Project([
                'name' => $args['name'],
                'description' => $args['description'] ?? ($template === null ? null : $this->scaffold->description($template)),
                'icon' => $args['icon'] ?? null,
                'locale' => $args['locale'] ?? Locale::DE->value,
            ]);
            $project->template_id = $template?->getKey();
            $project->save();

            if ($creator instanceof User) {
                $project->members()->attach($creator->getKey(), ['role_id' => AppSettings::projectCreatorRole()->id]);
                $this->addMembers($project, $creator, $args['members'] ?? []);
            }

            if ($template !== null) {
                $this->labels->copyFromTemplate($project, $template);
                $this->scaffold->apply($project, $template);
            }

            return $project;
        });

        // The planer revision is counted when the transaction is over.
        return $project->refresh();
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
