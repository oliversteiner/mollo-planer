<?php

namespace App\Planer;

use App\Enums\LabelColor;
use App\Enums\Locale;

/**
 * The content of a template: a planer-v2 document with the fixed values of
 * spec/format-v2.md (revision 0, only -new- ids, no members, no due dates,
 * every task "planned"). Reading it, building one that the app makes itself,
 * and the checks the sync of the built-in templates runs. The full check is
 * `tools/planer validate --template`.
 */
final class TemplateContent
{
    public const MAX_LABELS = 30;

    public const KEY_PATTERN = '/^[a-z0-9_-]{1,40}$/';

    public const NAME_MAX = 40;

    /**
     * An empty template: no outline, the given labels.
     *
     * @param  list<array{key: string, names: array<string, string>, category?: ?string, color: string}>  $labels
     * @return array<string, mixed>
     */
    public static function blank(string $name, ?string $description, string $locale, array $labels): array
    {
        return [
            'format' => 'planer-v2',
            'revision' => 0,
            'project' => self::project($name, $description, $locale, $labels),
            'milestones' => [],
        ];
    }

    /**
     * The labels, in order.
     *
     * @param  array<string, mixed>  $content
     * @return list<array{key: string, names: array<string, string>, category: ?string, color: string}>
     */
    public static function labels(array $content): array
    {
        $labels = [];

        foreach ((array) ($content['project']['labels'] ?? []) as $label) {
            $labels[] = [
                'key' => (string) ($label['key'] ?? ''),
                'names' => array_map('strval', (array) ($label['names'] ?? [])),
                'category' => isset($label['category']) && $label['category'] !== '' ? (string) $label['category'] : null,
                'color' => (string) ($label['color'] ?? 'gray'),
            ];
        }

        return $labels;
    }

    /**
     * The content with other labels.
     *
     * @param  array<string, mixed>  $content
     * @param  list<array{key: string, names: array<string, string>, category?: ?string, color: string}>  $labels
     * @return array<string, mixed>
     */
    public static function withLabels(array $content, array $labels): array
    {
        $content['project']['labels'] = array_map(self::label(...), $labels);

        return $content;
    }

    /**
     * The content with another name and description of the template.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function withMeta(array $content, string $name, ?string $description): array
    {
        $content['project']['name'] = $name;
        unset($content['project']['description']);

        if ($description !== null && $description !== '') {
            $content['project']['description'] = $description;
        }

        return $content;
    }

    /**
     * Milestones, todos and tasks by title (with the version of a milestone and
     * the label keys of a task).
     *
     * @param  array<string, mixed>  $content
     * @return list<array{title: string, version: ?string, todos: list<array{title: string, tasks: list<array{title: string, labels: list<string>}>}>}>
     */
    public static function outline(array $content): array
    {
        $milestones = [];

        foreach ((array) ($content['milestones'] ?? []) as $milestone) {
            $todos = [];

            foreach ((array) ($milestone['todos'] ?? []) as $todo) {
                $todos[] = [
                    'title' => (string) ($todo['title'] ?? ''),
                    'tasks' => array_map(static fn (array $task): array => [
                        'title' => (string) ($task['title'] ?? ''),
                        'labels' => array_map('strval', (array) ($task['labels'] ?? [])),
                    ], array_values(array_filter((array) ($todo['tasks'] ?? []), 'is_array'))),
                ];
            }

            $milestones[] = [
                'title' => (string) ($milestone['title'] ?? ''),
                'version' => isset($milestone['release']['version']) ? (string) $milestone['release']['version'] : null,
                'todos' => $todos,
            ];
        }

        return $milestones;
    }

    /**
     * What the sync of the built-in templates insists on (spec/format-v2.md 3.8,
     * 5.2, the label rules). Empty: the content is fine.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public static function problems(array $content): array
    {
        $problems = [];
        $project = (array) ($content['project'] ?? []);
        $locale = (string) ($project['locale'] ?? '');

        if (($content['format'] ?? null) !== 'planer-v2') {
            $problems[] = 'format must be planer-v2';
        }
        if (($content['revision'] ?? null) !== 0) {
            $problems[] = 'revision must be 0';
        }
        if (! is_string($project['name'] ?? null) || $project['name'] === '') {
            $problems[] = 'project.name is missing';
        }
        if (Locale::tryFrom($locale) === null) {
            $problems[] = 'project.locale must be de or en';
        }
        if (($project['members'] ?? []) !== []) {
            $problems[] = 'project.members must be empty';
        }

        array_push($problems, ...self::labelProblems($project['labels'] ?? null, $locale));

        $keys = array_column(self::labels($content), 'key');

        foreach ((array) ($content['milestones'] ?? []) as $m => $milestone) {
            if (! str_contains((string) ($milestone['id'] ?? ''), '-new-')) {
                $problems[] = "milestones.{$m}.id must be a -new- id";
            }
            if (($milestone['release']['status'] ?? 'planned') !== 'planned') {
                $problems[] = "milestones.{$m}.release.status must be planned";
            }
            foreach ((array) ($milestone['todos'] ?? []) as $t => $todo) {
                foreach ((array) ($todo['tasks'] ?? []) as $k => $task) {
                    $path = "milestones.{$m}.todos.{$t}.tasks.{$k}";

                    if (($task['status'] ?? 'planned') !== 'planned') {
                        $problems[] = "{$path}.status must be planned";
                    }
                    foreach (['assigneeEmail', 'dueDate', 'commit'] as $field) {
                        if (isset($task[$field])) {
                            $problems[] = "{$path}.{$field} must be missing";
                        }
                    }
                    foreach ((array) ($task['labels'] ?? []) as $key) {
                        if (! in_array($key, $keys, true)) {
                            $problems[] = "{$path}.labels: unknown label {$key}";
                        }
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private static function labelProblems(mixed $labels, string $locale): array
    {
        if (! is_array($labels) || $labels === []) {
            return ['project.labels needs at least one label'];
        }

        $problems = [];
        $keys = [];
        $names = [];

        if (count($labels) > self::MAX_LABELS) {
            $problems[] = 'project.labels has more than '.self::MAX_LABELS.' labels';
        }

        foreach (array_values($labels) as $i => $label) {
            $key = (string) ($label['key'] ?? '');

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                $problems[] = "project.labels.{$i}.key is not a-z, 0-9, _ or -, 1 to 40 characters";
            }
            if (isset($keys[$key])) {
                $problems[] = "project.labels.{$i}.key is used twice";
            }
            $keys[$key] = true;

            if (LabelColor::tryFrom((string) ($label['color'] ?? '')) === null) {
                $problems[] = "project.labels.{$i}.color is not one of the ten colours";
            }
            if (! isset($label['names'][$locale])) {
                $problems[] = "project.labels.{$i}.names needs the language of the template ({$locale})";
            }
            foreach ((array) ($label['names'] ?? []) as $language => $name) {
                $length = mb_strlen((string) $name);

                if (Locale::tryFrom((string) $language) === null || $length < 1 || $length > self::NAME_MAX) {
                    $problems[] = "project.labels.{$i}.names.{$language} is not 1 to 40 characters in de or en";
                }
                $lower = mb_strtolower((string) $name);
                if (isset($names[$language][$lower])) {
                    $problems[] = "project.labels.{$i}.names.{$language} is used twice";
                }
                $names[$language][$lower] = true;
            }
        }

        return $problems;
    }

    /**
     * @param  list<array{key: string, names: array<string, string>, category?: ?string, color: string}>  $labels
     * @return array<string, mixed>
     */
    private static function project(string $name, ?string $description, string $locale, array $labels): array
    {
        $project = ['name' => $name];

        if ($description !== null && $description !== '') {
            $project['description'] = $description;
        }

        return [...$project, 'locale' => $locale, 'members' => [], 'labels' => array_map(self::label(...), $labels)];
    }

    /**
     * @param  array{key: string, names: array<string, string>, category?: ?string, color: string}  $label
     * @return array<string, mixed>
     */
    private static function label(array $label): array
    {
        $out = ['key' => $label['key'], 'names' => $label['names']];

        if (($label['category'] ?? null) !== null && $label['category'] !== '') {
            $out['category'] = $label['category'];
        }

        return [...$out, 'color' => $label['color']];
    }
}
