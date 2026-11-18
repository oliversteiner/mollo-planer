<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Template;
use App\Planer\TemplateContent;
use App\Services\LabelInputs;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Creates an own template (name, description, language, labels, no outline) or
 * changes one (name, description, labels; language and outline stay). Labels
 * with a key stay, those without are new, those missing are gone. The content
 * is built so that it keeps the fixed values of a template (spec/format-v2.md
 * 3.8). Who may is checked by the policy (@can). Templates are not part of
 * a project, so they leave the planer revision alone.
 */
class SaveTemplate
{
    use ResolvesViewer;

    /**
     * @param  array{input: array{name: string, description?: ?string, locale: string, labels: list<array<string, mixed>>}}  $args
     *
     * @throws ValidationException
     */
    public function create(mixed $root, array $args, GraphQLContext $context): Template
    {
        $viewer = $this->viewer($context);
        $input = $args['input'];
        $name = $this->uniqueName($input['name']);
        $description = $this->description($input);
        $labels = LabelInputs::normalize($input['labels'], [], $input['locale'], 'input.labels', 1);

        return DB::transaction(function () use ($viewer, $name, $description, $input, $labels): Template {
            $template = new Template([
                'name' => $name,
                'description' => $description,
                'locale' => $input['locale'],
                'content' => TemplateContent::blank($name, $description, $input['locale'], $labels),
            ]);
            $template->creator()->associate($viewer);
            $template->save();

            return $template;
        });
    }

    /**
     * @param  array{id: int|string, input: array{name: string, description?: ?string, labels: list<array<string, mixed>>}}  $args
     *
     * @throws ValidationException
     */
    public function update(mixed $root, array $args): Template
    {
        return DB::transaction(function () use ($args): Template {
            $template = Template::query()->lockForUpdate()->findOrFail($args['id']);
            $input = $args['input'];
            $name = $this->uniqueName($input['name'], $template);
            $description = $this->description($input);
            $labels = LabelInputs::normalize($input['labels'], array_column($template->labels(), 'key'), $template->locale, 'input.labels', 1);

            $content = TemplateContent::withMeta($template->content, $name, $description);
            $content = TemplateContent::withLabels($content, $labels);
            $content['milestones'] = $this->withoutUnknownLabels($content['milestones'] ?? [], array_column($labels, 'key'));

            $template->forceFill(['name' => $name, 'description' => $description, 'content' => $content])->save();

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function description(array $input): ?string
    {
        $description = isset($input['description']) ? trim($input['description']) : '';

        return $description === '' ? null : $description;
    }

    /**
     * @throws ValidationException
     */
    private function uniqueName(string $name, ?Template $except = null): string
    {
        $name = trim($name);

        $taken = Template::query()->where('name', $name)->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))->exists();

        if ($taken) {
            throw ValidationException::withMessages(['input.name' => __('labels.template_name_taken')]);
        }

        return $name;
    }

    /**
     * Tasks of the outline lose the labels that the template no longer has.
     *
     * @param  list<array<string, mixed>>  $milestones
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function withoutUnknownLabels(array $milestones, array $keys): array
    {
        foreach ($milestones as $m => $milestone) {
            foreach ((array) ($milestone['todos'] ?? []) as $t => $todo) {
                foreach ((array) ($todo['tasks'] ?? []) as $k => $task) {
                    if (isset($task['labels'])) {
                        $milestones[$m]['todos'][$t]['tasks'][$k]['labels'] = array_values(array_intersect($task['labels'], $keys));
                    }
                }
            }
        }

        return $milestones;
    }
}
