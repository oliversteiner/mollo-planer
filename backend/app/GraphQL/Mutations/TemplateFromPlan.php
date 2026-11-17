<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\Template;
use App\Planer\Export;
use App\Planer\TemplateContent;
use App\Planer\Validator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use JsonException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use RuntimeException;

/**
 * Own templates from a planer file (docs/auftrag-m4.md §7): from a project
 * ("Save as template": export plus TemplateContent::fromDocument) and from an
 * uploaded file. The content keeps the fixed values of a template
 * (spec/format-v2.md 3.8). Needs templates.manage; the project needs planer.export.
 */
class TemplateFromPlan
{
    use ResolvesViewer;

    public function __construct(private readonly Export $export) {}

    /**
     * @param  array{projectId: int|string, name: string}  $args
     *
     * @throws ValidationException
     */
    public function fromProject(mixed $root, array $args, GraphQLContext $context): Template
    {
        $viewer = $this->viewer($context);
        $project = Project::query()->visibleTo($viewer)->find($args['projectId']) ?? throw new AuthorizationException;
        Gate::forUser($viewer)->authorize('export', $project);

        try {
            $document = $this->export->document($project);
        } catch (RuntimeException $error) {
            throw ValidationException::withMessages(['projectId' => $error->getMessage()]);
        }

        return $this->save($viewer, TemplateContent::fromDocument($document), $args['name'], 'name');
    }

    /**
     * @param  array{document: string}  $args
     *
     * @throws ValidationException
     */
    public function fromFile(mixed $root, array $args, GraphQLContext $context): Template
    {
        $viewer = $this->viewer($context);
        Gate::forUser($viewer)->authorize('create', Template::class);

        try {
            $document = json_decode($args['document'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValidationException::withMessages(['document' => __('planer.import.invalid', ['message' => $error->getMessage()])]);
        }

        if (! is_array($document)) {
            throw ValidationException::withMessages(['document' => __('planer.import.invalid', ['message' => 'not an object'])]);
        }

        $issues = Validator::errors($document);

        if ($issues !== []) {
            throw ValidationException::withMessages(['document' => __('planer.import.invalid', ['message' => "{$issues[0]['path']}: {$issues[0]['message']}"])]);
        }

        // A file may come from a project: it is made a template by the same rules.
        $content = TemplateContent::fromDocument($document);

        return $this->save($viewer, $content, $document['project']['name'], 'document');
    }

    /**
     * @param  array<string, mixed>  $content
     *
     * @throws ValidationException
     */
    private function save(mixed $viewer, array $content, string $name, string $field): Template
    {
        Gate::forUser($viewer)->authorize('create', Template::class);

        $name = trim($name);
        $description = isset($content['project']['description']) ? mb_substr((string) $content['project']['description'], 0, 500) : null;
        $content = TemplateContent::withMeta($content, $name, $description);
        $problems = TemplateContent::problems($content);

        if ($problems !== []) {
            throw ValidationException::withMessages([$field => __('labels.template_invalid', ['path' => 'template', 'problem' => $problems[0]])]);
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            throw ValidationException::withMessages([$field => __('validation.between.string', ['attribute' => 'name', 'min' => 2, 'max' => 60])]);
        }

        if (Template::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([$field => __('labels.template_name_taken')]);
        }

        return DB::transaction(function () use ($viewer, $content, $name, $description): Template {
            $template = new Template([
                'name' => $name,
                'description' => $description,
                'locale' => $content['project']['locale'],
                'content' => $content,
            ]);
            $template->creator()->associate($viewer);
            $template->save();

            return $template;
        });
    }
}
