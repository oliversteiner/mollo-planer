<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Idea;
use App\Models\Project;
use App\Models\Template;
use App\Planer\Canonical;
use App\Planer\Import;
use App\Planer\ImportMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use JsonException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Mutation.importPlan: writes a planer file into a project, or into a new
 * one. Who may import at all is decided here (planer.import in the project,
 * projects.create for a new project); every single change is checked again
 * by the importer.
 */
class ImportPlan
{
    use ResolvesViewer;

    public function __construct(private readonly Import $import) {}

    /**
     * @param  array{projectId?: int|string|null, document: string, mode?: string, dryRun?: bool, notifyAssignees?: bool, templateId?: int|string|null, ideaId?: int|string|null, appendOnly?: bool}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);
        $project = isset($args['projectId'])
            ? Project::query()->visibleTo($viewer)->find($args['projectId']) ?? throw new AuthorizationException
            : null;

        Gate::forUser($viewer)->authorize($project === null ? 'create' : 'import', $project ?? Project::class);

        $templateId = $args['templateId'] ?? null;

        if ($templateId !== null && $project !== null) {
            throw ValidationException::withMessages(['templateId' => __('planer.import.template_only_new')]);
        }

        if ($templateId !== null && ! Template::query()->whereKey($templateId)->exists()) {
            throw ValidationException::withMessages(['templateId' => __('planer.import.template_unknown')]);
        }

        $idea = isset($args['ideaId']) ? Idea::query()->find($args['ideaId']) : null;

        if (isset($args['ideaId']) && ($idea === null || ! Gate::forUser($viewer)->allows('view', $idea))) {
            throw ValidationException::withMessages(['ideaId' => __('planer.import.idea_unknown')]);
        }

        try {
            $document = json_decode($args['document'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return $this->failed(__('planer.import.invalid', ['message' => $error->getMessage()]), (bool) ($args['dryRun'] ?? false));
        }

        if (! is_array($document)) {
            return $this->failed(__('planer.import.invalid', ['message' => 'not an object']), (bool) ($args['dryRun'] ?? false));
        }

        $result = $this->import->run(
            $project,
            $document,
            ImportMode::from($args['mode'] ?? 'full'),
            (bool) ($args['dryRun'] ?? false),
            (bool) ($args['notifyAssignees'] ?? false),
            $viewer,
            (bool) ($args['appendOnly'] ?? false),
            $templateId === null ? null : (int) $templateId,
            $idea,
        );

        return [
            'ok' => $result->ok(),
            'dryRun' => $result->dryRun,
            'projectId' => $result->projectId,
            'changes' => $result->changes,
            'errors' => $result->errors,
            'idMap' => array_map(fn (string $from, string $to): array => ['from' => $from, 'to' => $to], array_keys($result->idMap), array_values($result->idMap)),
            'document' => $result->document === null ? null : Canonical::text($result->document),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $message, bool $dryRun): array
    {
        return ['ok' => false, 'dryRun' => $dryRun, 'projectId' => null, 'changes' => [], 'errors' => [['path' => '', 'kind' => 'invalid', 'message' => $message]], 'idMap' => [], 'document' => null];
    }
}
