<?php

namespace App\Planer;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;

/**
 * The canonical text of a planer-v2 document, byte for byte the same as
 * `canonicalText` in tools/planer/core/planer-canonical.mjs: fixed key order,
 * two spaces of indentation, a line end at the close; unset optional fields
 * (null) and empty label lists are left out; every description is written in
 * its canonical Markdown form. The same data always give the same text.
 */
final class Canonical
{
    private const ORDER = [
        'root' => ['$schema', 'format', 'revision', 'changed', 'project', 'milestones'],
        'project' => ['name', 'description', 'locale', 'members', 'labels'],
        'member' => ['email', 'role'],
        'label' => ['key', 'names', 'category', 'color'],
        'milestone' => ['id', 'title', 'description', 'dueDate', 'release', 'todos'],
        'release' => ['version', 'status'],
        'todo' => ['id', 'title', 'description', 'dueDate', 'tasks'],
        'task' => ['id', 'title', 'description', 'dueDate', 'status', 'assigneeEmail', 'labels', 'commit', 'userStory'],
        'story' => ['role', 'goal', 'benefit'],
    ];

    /**
     * @param  array<string, mixed>  $plan  A document that passed the validator
     */
    public static function text(array $plan): string
    {
        $json = json_encode(
            self::canonicalize($plan),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
        );

        // PHP indents with four spaces, the file with two.
        return preg_replace_callback('/^( +)/m', fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)), $json)."\n";
    }

    /**
     * A description in its canonical Markdown form. A text the strict reader
     * refuses stays as it is; the validator reports it.
     */
    public static function description(mixed $text): mixed
    {
        if (! is_string($text) || $text === '') {
            return $text;
        }

        try {
            $doc = MarkdownToTiptap::convert($text, Mode::Strict)->doc;
        } catch (InvalidMarkdown) {
            return $text;
        }

        return TiptapToMarkdown::convert($doc);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function canonicalize(array $plan): array
    {
        return self::order($plan, self::ORDER['root'], [
            'changed' => fn (mixed $changed): ?bool => $changed === true ? true : null,
            'project' => fn (array $project): array => self::order($project, self::ORDER['project'], [
                'description' => self::description(...),
                'members' => fn (array $members): array => array_map(fn (array $member): array => self::order($member, self::ORDER['member']), $members),
                'labels' => fn (array $labels): ?array => $labels === [] ? null : array_map(
                    fn (array $label): array => self::order($label, self::ORDER['label'], [
                        'names' => fn (array $names): array => self::order($names, ['de', 'en']),
                    ]),
                    $labels,
                ),
            ]),
            'milestones' => fn (array $milestones): array => array_map(fn (array $milestone): array => self::order($milestone, self::ORDER['milestone'], [
                'description' => self::description(...),
                'release' => fn (array $release): array => self::order($release, self::ORDER['release']),
                'todos' => fn (array $todos): array => array_map(fn (array $todo): array => self::order($todo, self::ORDER['todo'], [
                    'description' => self::description(...),
                    'tasks' => fn (array $tasks): array => array_map(fn (array $task): array => self::order($task, self::ORDER['task'], [
                        'description' => self::description(...),
                        'userStory' => fn (?array $story): ?array => $story === null ? null : self::order($story, self::ORDER['story']),
                        'labels' => fn (array $keys): ?array => $keys === [] ? null : array_values($keys),
                    ]), $tasks),
                ]), $todos),
            ]), $milestones),
        ]);
    }

    /**
     * The keys in fixed order, unknown keys last. null and missing mean the
     * same (not set) and are both left out.
     *
     * @param  array<string, mixed>  $node
     * @param  list<string>  $keys
     * @param  array<string, callable(mixed): mixed>  $children
     * @return array<string, mixed>
     */
    private static function order(array $node, array $keys, array $children = []): array
    {
        $result = [];
        $rest = array_values(array_diff(array_keys($node), $keys));

        foreach ([...$keys, ...$rest] as $key) {
            if (! array_key_exists($key, $node)) {
                continue;
            }

            if ($node[$key] === null) {
                continue;
            }

            $value = isset($children[$key]) ? $children[$key]($node[$key]) : $node[$key];

            if ($value === null) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
