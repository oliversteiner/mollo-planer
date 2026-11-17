<?php

namespace App\GraphQL\Mutations;

use App\Enums\Locale;
use App\Models\Project;
use App\Models\Role;
use App\Models\Template;
use App\Models\User;
use App\Planer\Import;
use App\Planer\ImportMode;
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
 * The importer does it (docs/auftrag-m4.md §4.3), as a partial import into the
 * new project: the template is a planer file like any other.
 */
class CreateProject
{
    public function __construct(
        private readonly ProjectMembership $membership,
        private readonly Import $import,
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
                'description' => $args['description'] ?? null,
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
                $this->applyTemplate($project, $template, $creator, isset($args['description']));
            }

            return $project;
        });

        // The planer revision is counted when the transaction is over.
        return $project->refresh();
    }

    /**
     * The template is written into the new project as a partial import: its
     * labels (a label without a name in the language of the project gets the
     * template's name), its outline, and its description when the person gave none.
     *
     * @throws ValidationException
     */
    private function applyTemplate(Project $project, Template $template, ?User $creator, bool $hasOwnDescription): void
    {
        $document = $template->content ?? [];
        $locale = $project->locale->value;

        $document['revision'] = 0;
        $document['project']['name'] = $project->name;
        $document['project']['locale'] = $locale;
        $document['project']['members'] = [];

        foreach ($document['project']['labels'] ?? [] as $index => $label) {
            $document['project']['labels'][$index]['names'][$locale] ??= $label['names'][$template->locale] ?? (string) reset($label['names']);
        }

        if ($hasOwnDescription) {
            unset($document['project']['description']);
        }

        $result = $this->import->run($project, $document, ImportMode::Partial, false, false, $creator ?? throw ValidationException::withMessages(['input.templateId' => __('labels.template_unknown')]));

        if ($result->errors !== []) {
            throw ValidationException::withMessages(['input.templateId' => __('labels.template_invalid', ['path' => $result->errors[0]['path'], 'problem' => $result->errors[0]['message']])]);
        }
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
