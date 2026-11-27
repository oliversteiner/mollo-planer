<?php

namespace App\Tools\Support;

use App\Support\ClosestName;
use App\Tools\Tool;
use Illuminate\Validation\ValidationException;

/**
 * What the tools insist on before they work (Auftrag 49 B): a parameter the tool does not have is an error with a
 * suggestion, never silently dropped (a filter that does nothing gives the whole plan), and an ID of the wrong kind
 * ("t-396" where a task is meant) is an error that says what it is and where to look.
 */
final class ToolArguments
{
    private const KINDS = ['m' => 'milestone', 't' => 'todo', 'k' => 'task', 'c' => 'comment'];

    /** Parameters people try that a tool does not have, and what to do instead. */
    private const HINTS = [
        'export_plan' => [
            'todo_ids' => 'export_plan filters by milestone_ids, version, task_status or include_released; for the tasks of one todo use list_tasks with todo_ids.',
            'task_ids' => 'export_plan filters by milestone_ids, version, task_status or include_released; for single tasks use list_tasks with ids.',
        ],
        'list_tasks' => [
            'assignee_email' => 'Use assignee: "me" or an email.',
        ],
    ];

    /**
     * A single value where a list is meant ("review" for ["review"]): the same call that would have failed works.
     *
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function singleToList(array $arguments, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($arguments[$key]) && is_string($arguments[$key])) {
                $arguments[$key] = [$arguments[$key]];
            }
        }

        return $arguments;
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @throws ValidationException
     */
    public static function rejectUnknown(Tool $tool, array $arguments): void
    {
        self::checkObject($tool->name(), $tool->schema(), $arguments, '');
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<mixed>  $value
     */
    private static function checkObject(string $tool, array $schema, array $value, string $where): void
    {
        $properties = $schema['properties'] ?? null;

        if (! is_array($properties) || $properties === []) {
            return;
        }

        foreach ($value as $name => $item) {
            if (! is_string($name)) {
                continue;
            }

            if (! array_key_exists($name, $properties)) {
                throw ValidationException::withMessages([$where.$name => self::unknownMessage($tool, $name, array_keys($properties), $where)]);
            }

            $child = $properties[$name];

            if (is_array($item) && ($child['type'] ?? null) === 'object') {
                self::checkObject($tool, $child, $item, "{$where}{$name}.");
            } elseif (is_array($item) && ($child['type'] ?? null) === 'array' && is_array($child['items'] ?? null) && ($child['items']['type'] ?? null) === 'object') {
                foreach ($item as $index => $entry) {
                    if (is_array($entry)) {
                        self::checkObject($tool, $child['items'], $entry, "{$where}{$name}[{$index}].");
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $known
     */
    private static function unknownMessage(string $tool, string $name, array $known, string $where): string
    {
        $path = $where.$name;
        $hint = self::HINTS[$tool][$name] ?? null;
        $suggestion = ClosestName::of($name, $known);
        $text = "`{$path}` is not a parameter of {$tool}.";

        if ($hint !== null) {
            return "{$text} {$hint}";
        }

        return $suggestion !== null
            ? "{$text} Did you mean `{$where}{$suggestion}`?"
            : "{$text} Parameters: ".implode(', ', $known).'.';
    }

    /**
     * The number of an ID of one kind: "k-12" or "12" for a task. A todo, a milestone or a comment where a task is
     * meant, a new ID of a document and anything that is no ID are errors that say so.
     *
     * @param  'm'|'t'|'k'|'c'  $kind
     *
     * @throws ValidationException
     */
    public static function id(mixed $value, string $kind, string $parameter): int
    {
        $text = trim((string) $value);

        if (preg_match('/^(?:'.$kind.'-)?(\d+)$/', $text, $match) === 1) {
            return (int) $match[1];
        }

        $expected = self::KINDS[$kind];

        if (preg_match('/^([mtkc])-new-\d+$/', $text) === 1) {
            $message = "{$text} is a new item of a document, it has no ID yet. {$parameter} takes the ID of an existing {$expected} ({$kind}-<number>).";
        } elseif (preg_match('/^([mtkc])-\d+$/', $text, $other) === 1) {
            $message = "{$text} is a ".self::KINDS[$other[1]].", not a {$expected}. {$parameter} takes {$expected} IDs ({$kind}-<number>).".self::where($kind);
        } else {
            $message = "\"{$text}\" is not an ID. {$parameter} takes {$expected} IDs ({$kind}-<number>, or just the number).";
        }

        throw ValidationException::withMessages([$parameter => $message]);
    }

    /**
     * @param  iterable<mixed>  $values
     * @param  'm'|'t'|'k'|'c'  $kind
     * @return list<int>
     *
     * @throws ValidationException
     */
    public static function ids(iterable $values, string $kind, string $parameter): array
    {
        $numbers = [];

        foreach ($values as $value) {
            $numbers[] = self::id($value, $kind, $parameter);
        }

        return $numbers;
    }

    /** Where to look when a todo or a milestone was given for a task. */
    private static function where(string $kind): string
    {
        return $kind === 'k' ? ' For what is inside a milestone or todo use export_plan or list_tasks with milestone_ids.' : '';
    }
}
