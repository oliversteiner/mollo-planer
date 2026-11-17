<?php

namespace App\Planer;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator as SchemaValidator;

/**
 * Checks a planer-v2 document (spec/format-v2.md §5), the same two steps as
 * `validatePlaner` in tools/planer: the schema (spec/planer-v2.schema.json,
 * form, types, limits, patterns), then, only when the schema passes, the
 * rules the schema cannot express: unique ids, labels and members that
 * exist, release and commit rules, and the Markdown of every description
 * (strict reader).
 *
 * An issue is `{path, severity, message}`; the path points at the place, for
 * example `milestones[0].todos[0].tasks[1].commit`. PHP and tools/planer
 * report the same kinds of issue at the same paths for every file in
 * spec/fixtures; the texts may differ.
 *
 * One limit of the schema library (opis): inside one object it reports the
 * values that are wrong, but a missing required field or an unknown field only
 * when nothing else in that object failed. The fixtures name one such error
 * each; a file with several kinds at once shows them over several checks.
 */
final class Validator
{
    private const SCHEMA_FILE = '../spec/planer-v2.schema.json';

    /** Read and parsed once: the library keeps what it parsed with the schema object. */
    private static ?object $schema = null;

    private static ?SchemaValidator $validator = null;

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{path: string, severity: string, message: string}>
     */
    public static function validate(array $document): array
    {
        $issues = self::schemaIssues($document);

        if ($issues !== []) {
            return $issues;
        }

        return [...self::semantics($document), ...self::markdown($document)];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{path: string, severity: string, message: string}>
     */
    public static function errors(array $document): array
    {
        return array_values(array_filter(self::validate($document), fn (array $issue): bool => $issue['severity'] === 'error'));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{path: string, severity: string, message: string}>
     */
    private static function schemaIssues(array $document): array
    {
        self::$schema ??= json_decode((string) file_get_contents(base_path(self::SCHEMA_FILE)), false, 512, JSON_THROW_ON_ERROR);
        self::$validator ??= (new SchemaValidator)->setMaxErrors(200);

        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = self::$validator->validate($data, self::$schema);

        if ($result->isValid()) {
            return [];
        }

        $issues = [];
        self::collect($result->error(), $issues);

        return $issues;
    }

    /**
     * Only the leaves of the error tree, where the keyword failed. A missing or an
     * unknown field is reported at the field itself, like tools/planer does.
     *
     * @param  list<array{path: string, severity: string, message: string}>  $issues
     */
    private static function collect(?ValidationError $error, array &$issues): void
    {
        if ($error === null) {
            return;
        }

        $subErrors = $error->subErrors();

        if ($subErrors !== []) {
            foreach ($subErrors as $sub) {
                self::collect($sub, $issues);
            }

            return;
        }

        $path = self::path($error->data()->fullPath());
        $args = $error->args();

        $fields = match ($error->keyword()) {
            'required' => $args['missing'] ?? [],
            'additionalProperties' => $args['properties'] ?? [],
            default => null,
        };

        if ($fields === null) {
            $issues[] = ['path' => $path, 'severity' => 'error', 'message' => self::message($error)];

            return;
        }

        foreach ($fields as $field) {
            $issues[] = ['path' => self::join($path, (string) $field), 'severity' => 'error', 'message' => self::message($error)];
        }
    }

    private static function message(ValidationError $error): string
    {
        return (new ErrorFormatter)->formatErrorMessage($error);
    }

    /**
     * @param  array<array-key, int|string>  $parts
     */
    private static function path(array $parts): string
    {
        $path = '';

        foreach ($parts as $part) {
            $path = is_int($part) ? "{$path}[{$part}]" : self::join($path, $part);
        }

        return $path;
    }

    private static function join(string $path, string $key): string
    {
        return $path === '' ? $key : "{$path}.{$key}";
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array{path: string, severity: string, message: string}>
     */
    private static function semantics(array $plan): array
    {
        $issues = [];
        $add = function (string $path, string $message) use (&$issues): void {
            $issues[] = ['path' => $path, 'severity' => 'error', 'message' => $message];
        };

        $firstSeen = [];
        $claimId = function (string $id, string $path) use (&$firstSeen, $add): void {
            if (isset($firstSeen[$id])) {
                $add($path, "ID {$id} is used twice (first at {$firstSeen[$id]})");
            } else {
                $firstSeen[$id] = $path;
            }
        };

        $project = $plan['project'];
        $members = array_flip(array_map(fn (array $member): string => mb_strtolower($member['email']), $project['members']));

        $labelKeys = [];
        $labelNames = [];

        foreach ($project['labels'] ?? [] as $li => $label) {
            $lp = "project.labels[{$li}]";

            if (isset($labelKeys[$label['key']])) {
                $add("{$lp}.key", "Key {$label['key']} is used twice");
            }
            $labelKeys[$label['key']] = true;

            if (! isset($label['names'][$project['locale']])) {
                $add("{$lp}.names", "The name in the language of the project ({$project['locale']}) is missing");
            }

            foreach ($label['names'] as $lang => $name) {
                $seen = "{$lang}:{$name}";

                if (isset($labelNames[$seen])) {
                    $add("{$lp}.names.{$lang}", "The name {$name} is used twice in {$lang}");
                }
                $labelNames[$seen] = true;
            }
        }

        foreach ($plan['milestones'] as $mi => $milestone) {
            $mp = "milestones[{$mi}]";
            $claimId($milestone['id'], "{$mp}.id");

            $released = ($milestone['release']['status'] ?? null) === 'released';
            $open = 0;

            foreach ($milestone['todos'] as $ti => $todo) {
                $tp = "{$mp}.todos[{$ti}]";
                $claimId($todo['id'], "{$tp}.id");

                foreach ($todo['tasks'] as $ki => $task) {
                    $kp = "{$tp}.tasks[{$ki}]";
                    $claimId($task['id'], "{$kp}.id");

                    if ($task['status'] !== 'done') {
                        $open++;
                    }

                    $assignee = $task['assigneeEmail'] ?? null;

                    if ($assignee !== null && $assignee !== '' && ! isset($members[mb_strtolower($assignee)])) {
                        $add("{$kp}.assigneeEmail", "{$assignee} is not a member of the project");
                    }

                    foreach ($task['labels'] ?? [] as $li => $key) {
                        if (! isset($labelKeys[$key])) {
                            $add("{$kp}.labels[{$li}]", "{$key} is not a label of the project");
                        }
                    }

                    $commit = $task['commit'] ?? null;

                    if ($commit === 'none' && $task['status'] !== 'done') {
                        $add("{$kp}.commit", '"none" is only allowed for tasks that are done');
                    }

                    if ($released && $task['status'] === 'done' && $commit === null) {
                        $add("{$kp}.commit", "A done task in the released version {$milestone['release']['version']} needs a hash or \"none\"");
                    }
                }
            }

            if ($released && $open > 0) {
                $add($mp, "Version {$milestone['release']['version']} is released but has {$open} task(s) that are not done");
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array{path: string, severity: string, message: string}>
     */
    private static function markdown(array $plan): array
    {
        $issues = [];
        $check = function (mixed $text, string $path) use (&$issues): void {
            if (! is_string($text)) {
                return;
            }

            try {
                MarkdownToTiptap::convert($text, Mode::Strict);
            } catch (InvalidMarkdown $error) {
                foreach ($error->errors as $warning) {
                    $issues[] = ['path' => $path, 'severity' => 'error', 'message' => "{$warning->kind} (line {$warning->line}): {$warning->excerpt}"];
                }
            }
        };

        $check($plan['project']['description'] ?? null, 'project.description');

        foreach ($plan['milestones'] as $mi => $milestone) {
            $mp = "milestones[{$mi}]";
            $check($milestone['description'] ?? null, "{$mp}.description");

            foreach ($milestone['todos'] as $ti => $todo) {
                $tp = "{$mp}.todos[{$ti}]";
                $check($todo['description'] ?? null, "{$tp}.description");

                foreach ($todo['tasks'] as $ki => $task) {
                    $check($task['description'] ?? null, "{$tp}.tasks[{$ki}].description");
                }
            }
        }

        return $issues;
    }
}
