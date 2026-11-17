<?php

namespace App\Services;

use App\Enums\LabelColor;
use App\Models\Label;
use App\Models\Project;
use App\Models\Template;
use App\Planer\PlanerRevision;
use App\Planer\TemplateContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that writes the labels of a project: setting them all at
 * once (the editor), copying them from a template for a new project, and
 * taking over the labels of a template for an existing one. Every change
 * counts once for the planer file (a transaction, one revision); deleting a
 * label takes it from the tasks too.
 */
class ProjectLabels
{
    public function __construct(private readonly PlanerRevision $revision) {}

    /**
     * Gives the project exactly these labels: those with a key are kept and
     * changed, those without are new, the others are deleted.
     *
     * @param  list<array<string, mixed>>  $labels  As the editor sends them
     *
     * @throws ValidationException
     */
    public function set(Project $project, array $labels, string $field = 'labels'): void
    {
        DB::transaction(function () use ($project, $labels, $field): void {
            $existing = $project->labels()->get();
            $normalized = LabelInputs::normalize($labels, $existing->pluck('key')->all(), $project->locale->value, $field);

            $this->write($project, $existing->keyBy('key'), $normalized, []);
            $this->revision->touch($project->getKey());
        });
    }

    /**
     * The labels of a planer file: exactly these, with the keys of the file
     * (a label that is new gets the key the file gives it). Those with a key
     * that exists are kept and changed, the others are created. With
     * $keepOthers the labels the file does not list stay (partial import),
     * otherwise they are deleted, from the tasks too. Counts as one change.
     *
     * @param  list<array{key: string, names: array<string, string>, category: ?string, color: string}>  $labels
     * @return bool Whether anything changed
     *
     * @throws ValidationException
     */
    public function replace(Project $project, array $labels, bool $keepOthers): bool
    {
        $existing = $project->labels()->get();
        $listed = array_column($labels, 'key');
        $keep = $keepOthers ? $existing->pluck('key')->reject(fn (string $key): bool => in_array($key, $listed, true))->values()->all() : [];
        $this->checkTotal(count($labels) + count($keep));

        $wanted = [...array_map(fn (array $label): array => $this->normalized($label), $labels), ...array_map(fn (string $key): array => $this->asData($existing->firstWhere('key', $key)), $keep)];
        $current = $existing->map(fn (Label $label): array => $this->asData($label))->values()->all();

        if ($wanted === $current) {
            return false;
        }

        $this->write($project, $existing->keyBy('key'), $labels, $keep);
        $this->revision->touch($project->getKey());

        return true;
    }

    /**
     * @param  array{key: string, names: array<string, string>, category: ?string, color: string}  $label
     * @return array{key: string, names: array<string, string>, category: ?string, color: string}
     */
    private function normalized(array $label): array
    {
        $names = $label['names'];
        ksort($names);

        return ['key' => $label['key'], 'names' => $names, 'category' => $label['category'] ?? null, 'color' => $label['color']];
    }

    /**
     * A new project gets the labels of the template, with all languages. A label
     * without a name in the language of the project gets the name of the template's language.
     *
     * @throws ValidationException
     */
    public function copyFromTemplate(Project $project, Template $template): void
    {
        $this->write($project, collect(), $this->fromTemplate($template, $project->locale->value), []);
    }

    /**
     * The labels of the template in an existing project. APPEND adds those
     * whose key is missing. REPLACE sets the labels of the template: those with
     * the same key are updated (the tasks keep them), labels with tasks that are
     * not in the template stay at the end, the others are deleted.
     *
     * @throws ValidationException
     */
    public function applyTemplate(Project $project, Template $template, bool $replace): void
    {
        DB::transaction(function () use ($project, $template, $replace): void {
            $existing = $project->labels()->withCount('tasks')->get()->keyBy('key');
            $wanted = $this->fromTemplate($template, $project->locale->value);

            if ($replace) {
                $keep = $existing->diffKeys(array_flip(array_column($wanted, 'key')))->filter(fn (Label $label): bool => $label->tasks_count > 0);
                $this->checkTotal(count($wanted) + $keep->count());
                $this->write($project, $existing, $wanted, $keep->keys()->all());
            } else {
                $added = array_values(array_filter($wanted, fn (array $label): bool => ! $existing->has($label['key'])));
                $this->checkTotal($existing->count() + count($added));
                $this->write($project, $existing, [...$existing->map(fn (Label $label): array => $this->asData($label))->values()->all(), ...$added], []);
            }

            $this->revision->touch($project->getKey());
        });
    }

    /**
     * @return list<array{key: string, names: array<string, string>, category: ?string, color: string}>
     */
    private function fromTemplate(Template $template, string $locale): array
    {
        return array_map(static function (array $label) use ($template, $locale): array {
            $label['names'][$locale] ??= $label['names'][$template->locale] ?? (string) reset($label['names']);

            return $label;
        }, $template->labels());
    }

    /**
     * @return array{key: string, names: array<string, string>, category: ?string, color: string}
     */
    private function asData(Label $label): array
    {
        $names = (array) $label->names;
        ksort($names);

        return ['key' => $label->key, 'names' => $names, 'category' => $label->category, 'color' => $label->color->value];
    }

    /**
     * Writes the labels in this order: existing ones with a key are updated, new ones
     * created, existing ones that are neither listed nor to be kept are deleted; the ones to
     * keep come last.
     *
     * @param  Collection<string, Label>  $existing  By key
     * @param  list<array{key: string, names: array<string, string>, category: ?string, color: string}>  $labels
     * @param  list<string>  $keep  Keys of existing labels to leave as they are, after the others
     */
    private function write(Project $project, $existing, array $labels, array $keep): void
    {
        $listed = array_column($labels, 'key');

        $existing->each(function (Label $label) use ($listed, $keep): void {
            if (! in_array($label->key, $listed, true) && ! in_array($label->key, $keep, true)) {
                $label->delete();
            }
        });

        $position = 0;

        foreach ($labels as $data) {
            $attributes = ['names' => $data['names'], 'category' => $data['category'], 'color' => LabelColor::from($data['color']), 'position' => $position++];

            if ($existing->has($data['key'])) {
                $existing[$data['key']]->fill($attributes)->save();
            } else {
                $label = new Label($attributes);
                $label->forceFill(['project_id' => $project->getKey(), 'key' => $data['key']])->save();
            }
        }

        foreach ($keep as $key) {
            $existing[$key]->forceFill(['position' => $position++])->save();
        }
    }

    private function checkTotal(int $count): void
    {
        if ($count > TemplateContent::MAX_LABELS) {
            throw ValidationException::withMessages(['labels' => __('labels.too_many', ['max' => TemplateContent::MAX_LABELS])]);
        }
    }
}
