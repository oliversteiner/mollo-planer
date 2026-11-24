<?php

namespace App\Planer;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Support\ClosestName;
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

    /** The same schema for an excerpt that goes back with mode "partial" (see partialSchema()). */
    private static ?object $partialSchema = null;

    private static ?SchemaValidator $validator = null;

    /**
     * With $partial the document is an excerpt for the import mode "partial" (Auftrag 49 A, spec/format-v2.md
     * «Ausschnitt»): an existing element needs only its id and the fields that change, `revision` and everything under
     * `project` may be missing, new elements (`m-new-1`) need what they always need. Labels and assignees are then
     * resolved against the project by the import, not against the document.
     *
     * @param  array<string, mixed>  $document
     * @return list<array{path: string, severity: string, message: string, code?: string, email?: string}>
     */
    public static function validate(array $document, bool $partial = false): array
    {
        $issues = self::schemaIssues($document, $partial);

        if ($issues !== []) {
            return $issues;
        }

        return [...self::semantics($document, $partial), ...self::markdown($document)];
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
    private static function schemaIssues(array $document, bool $partial): array
    {
        self::$schema ??= json_decode((string) file_get_contents(base_path(self::SCHEMA_FILE)), false, 512, JSON_THROW_ON_ERROR);
        self::$partialSchema ??= self::partialSchema(self::$schema);
        self::$validator ??= (new SchemaValidator)->setMaxErrors(200);

        $data = json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = self::$validator->validate($data, $partial ? self::$partialSchema : self::$schema);

        if ($result->isValid()) {
            return [];
        }

        $issues = [];
        self::collect($result->error(), $issues);

        return $issues;
    }

    /**
     * The schema of an excerpt, made from the one schema (there is no second description of the form): `revision`,
     * `project`, the fields of `project` and the lists `milestones`, `todos`, `tasks` may be missing; a milestone, todo
     * or task needs only its id unless the id is new (`m-new-1`), then its required fields are required as always.
     */
    private static function partialSchema(object $schema): object
    {
        $partial = json_decode((string) json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $partial->required = ['format'];
        // A schema object is known to the library by its id: the excerpt gets its own.
        $partial->{'$id'} = ($schema->{'$id'} ?? 'urn:mollo-planer:planer-v2').'-partial';
        $defs = $partial->{'$defs'};
        $defs->project->required = [];

        foreach (['milestone' => 'todos', 'todo' => 'tasks', 'task' => null] as $level => $children) {
            $whenNew = array_values(array_filter($defs->{$level}->required, fn (string $field): bool => $field !== $children));
            $defs->{$level}->required = ['id'];
            $defs->{$level}->allOf = [(object) [
                'if' => (object) ['properties' => (object) ['id' => (object) ['pattern' => '^[mtk]-new-']], 'required' => ['id']],
                'then' => (object) ['required' => $whenNew],
            ]];
        }

        return $partial;
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
            $message = $error->keyword() === 'additionalProperties' ? self::unknownField($error, (string) $field) : self::message($error);
            $issues[] = ['path' => self::join($path, (string) $field), 'severity' => 'error', 'message' => $message];
        }
    }

    /**
     * The message in the language of the request. A kind that has no text here keeps the original text of the library.
     */
    private static function message(ValidationError $error): string
    {
        $args = $error->args();

        return match ($error->keyword()) {
            'required' => __('planer.validation.required'),
            'additionalProperties' => __('planer.validation.unknown_field'),
            'type' => __('planer.validation.type', ['expected' => implode(' / ', (array) ($args['expected'] ?? []))]),
            'enum' => __('planer.validation.enum'),
            'const' => __('planer.validation.enum'),
            'pattern' => self::patternMessage((string) ($args['pattern'] ?? '')),
            'minLength' => __('planer.validation.min_length', ['min' => $args['min'] ?? '', 'length' => $args['length'] ?? '']),
            'maxLength' => __('planer.validation.max_length', ['max' => $args['max'] ?? '', 'length' => $args['length'] ?? '']),
            'minItems' => __('planer.validation.min_items', ['min' => $args['min'] ?? '', 'count' => $args['count'] ?? '']),
            'maxItems' => __('planer.validation.max_items', ['max' => $args['max'] ?? '', 'count' => $args['count'] ?? '']),
            'minimum' => __('planer.validation.minimum', ['min' => $args['min'] ?? '']),
            'format' => match ($args['format'] ?? null) {
                'date' => __('planer.validation.date'),
                'email' => __('planer.validation.email'),
                default => (new ErrorFormatter)->formatErrorMessage($error),
            },
            default => (new ErrorFormatter)->formatErrorMessage($error),
        };
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

    /**
     * The id patterns of the schema name their prefix ("^k-(new-)?[0-9]+$"): the message says what is expected, so
     * "k-new-47a" is not just "wrong format".
     */
    private static function patternMessage(string $pattern): string
    {
        if (preg_match('/^\^([mtk])-\(new-\)\?\[0-9\]\+\$$/', $pattern, $match) === 1) {
            return __('planer.validation.id_pattern', ['prefix' => $match[1]]);
        }

        return __('planer.validation.pattern');
    }

    /** "assignee" is not a field; the message names the field of the schema that is meant, when there is one. */
    private static function unknownField(ValidationError $error, string $field): string
    {
        $data = $error->schema()->info()->data();
        $known = is_object($data) ? array_keys((array) ($data->properties ?? [])) : [];
        $suggestion = ClosestName::of($field, $known);

        return $suggestion === null
            ? __('planer.validation.unknown_field')
            : __('planer.validation.unknown_field_suggest', ['field' => $field, 'suggestion' => $suggestion]);
    }

    private static function join(string $path, string $key): string
    {
        return $path === '' ? $key : "{$path}.{$key}";
    }

    /**
     * In an excerpt (partial) the document is not the whole plan: assignees and labels are checked by the import
     * against the project, and the rules of a release against the result (ReleaseRules), not against the document.
     *
     * @param  array<string, mixed>  $plan
     * @return list<array{path: string, severity: string, message: string}>
     */
    private static function semantics(array $plan, bool $partial = false): array
    {
        $issues = [];
        $add = function (string $path, string $message) use (&$issues): void {
            $issues[] = ['path' => $path, 'severity' => 'error', 'message' => $message];
        };

        $hint = function (string $path, string $message) use (&$issues): void {
            $issues[] = ['path' => $path, 'severity' => 'warning', 'message' => $message];
        };

        $firstSeen = [];
        $claimId = function (string $id, string $path) use (&$firstSeen, $add): void {
            if (isset($firstSeen[$id])) {
                $add($path, __('planer.validation.id_twice', ['id' => $id, 'first' => $firstSeen[$id]]));
            } else {
                $firstSeen[$id] = $path;
            }
        };

        $project = $plan['project'] ?? [];
        $members = array_flip(array_map(fn (array $member): string => mb_strtolower($member['email']), $project['members'] ?? []));

        $labelKeys = [];
        $labelNames = [];

        foreach ($project['labels'] ?? [] as $li => $label) {
            $lp = "project.labels[{$li}]";

            if (isset($labelKeys[$label['key']])) {
                $add("{$lp}.key", __('planer.validation.label_key_twice', ['key' => $label['key']]));
            }
            $labelKeys[$label['key']] = true;

            if (isset($project['locale']) && ! isset($label['names'][$project['locale']])) {
                $add("{$lp}.names", __('planer.validation.label_name_missing', ['language' => $project['locale']]));
            }

            foreach ($label['names'] as $lang => $name) {
                $seen = "{$lang}:{$name}";

                if (isset($labelNames[$seen])) {
                    $add("{$lp}.names.{$lang}", __('planer.validation.label_name_twice', ['name' => $name, 'language' => $lang]));
                }
                $labelNames[$seen] = true;
            }
        }

        foreach ($plan['milestones'] ?? [] as $mi => $milestone) {
            $mp = "milestones[{$mi}]";
            $claimId($milestone['id'], "{$mp}.id");

            $released = ! $partial && ($milestone['release']['status'] ?? null) === 'released';
            $open = 0;

            // The date of the release is always optional: a hint when a released version has none, an error for a planned one.
            if ($released && ! isset($milestone['release']['date'])) {
                $hint("{$mp}.release.date", __('planer.validation.release_date_missing', ['version' => $milestone['release']['version']]));
            }

            if (($milestone['release']['status'] ?? null) === 'planned' && isset($milestone['release']['date'])) {
                $add("{$mp}.release.date", __('planer.validation.release_date_planned', ['version' => $milestone['release']['version']]));
            }

            foreach ($milestone['todos'] ?? [] as $ti => $todo) {
                $tp = "{$mp}.todos[{$ti}]";
                $claimId($todo['id'], "{$tp}.id");

                foreach ($todo['tasks'] ?? [] as $ki => $task) {
                    $kp = "{$tp}.tasks[{$ki}]";
                    $claimId($task['id'], "{$kp}.id");
                    $status = $task['status'] ?? null;

                    if ($status !== 'done') {
                        $open++;
                    }

                    $assignee = $task['assigneeEmail'] ?? null;

                    // The import turns this issue into a warning (the task goes on without an assignee); the code tells them apart.
                    if (! $partial && $assignee !== null && $assignee !== '' && ! isset($members[mb_strtolower($assignee)])) {
                        $issues[] = [
                            'path' => "{$kp}.assigneeEmail",
                            'severity' => 'error',
                            'message' => __('planer.validation.assignee_not_member', ['email' => $assignee]),
                            'code' => 'assignee_not_member',
                            'email' => $assignee,
                        ];
                    }

                    foreach ($partial ? [] : $task['labels'] ?? [] as $li => $key) {
                        if (! isset($labelKeys[$key])) {
                            $add("{$kp}.labels[{$li}]", __('planer.validation.label_unknown', ['key' => $key]));
                        }
                    }

                    $commit = $task['commit'] ?? null;

                    if ($commit === 'none' && $status !== null && $status !== 'done') {
                        $add("{$kp}.commit", __('planer.validation.commit_none'));
                    }

                    if ($released && $status === 'done' && $commit === null) {
                        $add("{$kp}.commit", __('planer.validation.commit_missing', ['version' => $milestone['release']['version']]));
                    }
                }
            }

            if ($released && $open > 0) {
                $add($mp, __('planer.validation.release_open', ['version' => $milestone['release']['version'], 'count' => $open]));
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
                    $issues[] = ['path' => $path, 'severity' => 'error', 'message' => __('planer.validation.markdown', ['kind' => $warning->kind, 'line' => $warning->line, 'excerpt' => $warning->excerpt])];
                }
            }
        };

        $check($plan['project']['description'] ?? null, 'project.description');

        foreach ($plan['milestones'] ?? [] as $mi => $milestone) {
            $mp = "milestones[{$mi}]";
            $check($milestone['description'] ?? null, "{$mp}.description");

            foreach ($milestone['todos'] ?? [] as $ti => $todo) {
                $tp = "{$mp}.todos[{$ti}]";
                $check($todo['description'] ?? null, "{$tp}.description");

                foreach ($todo['tasks'] ?? [] as $ki => $task) {
                    $check($task['description'] ?? null, "{$tp}.tasks[{$ki}].description");
                }
            }
        }

        return $issues;
    }
}
