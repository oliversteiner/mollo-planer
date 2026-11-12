<?php

namespace App\Support\DevelopmentHistory;

use RuntimeException;

/**
 * Reads docs/entwicklung.md: milestones (## v0.1 …), todos (### …) and tasks
 * (- [x] [kind] title). Indented lines below a task are its description; plain
 * lines directly below a milestone or todo heading are its description.
 *
 * Other "## " sections (for example "Aufbau") are not milestones and are skipped.
 */
final class HistoryMarkdown
{
    public const KINDS = ['feature', 'bug', 'planning', 'research', 'design', 'blocked'];

    /**
     * @return array{milestones: list<array{title: string, description: ?string, todos: list<array{title: string, description: ?string, tasks: list<array{title: string, description: ?string, done: bool, kind: string}>}>}>}
     */
    public static function parse(string $markdown): array
    {
        $milestones = [];
        $inMilestone = false;
        $inTodo = false;
        $inTask = false;
        /** Where plain text lines belong: 'milestone', 'todo' or 'task'. */
        $context = null;

        foreach (preg_split('/\R/', $markdown) ?: [] as $index => $line) {
            $number = $index + 1;
            $trimmed = rtrim($line);

            if (preg_match('/^## v\d+(?:\.\d+)*\s/', $trimmed) === 1) {
                $milestones[] = ['title' => substr($trimmed, 3), 'description' => null, 'todos' => []];
                $inMilestone = true;
                $inTodo = false;
                $inTask = false;
                $context = 'milestone';

                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                $inMilestone = false;
                $inTodo = false;
                $inTask = false;
                $context = null;

                continue;
            }

            if (! $inMilestone) {
                continue;
            }

            $m = count($milestones) - 1;

            if (str_starts_with($trimmed, '### ')) {
                $milestones[$m]['todos'][] = ['title' => substr($trimmed, 4), 'description' => null, 'tasks' => []];
                $inTodo = true;
                $inTask = false;
                $context = 'todo';

                continue;
            }

            if (preg_match('/^- \[( |x)\] \[(\w+)\] (.+)$/', $trimmed, $match) === 1) {
                if (! $inTodo) {
                    throw new RuntimeException("Line {$number}: task without a todo.");
                }
                if (! in_array($match[2], self::KINDS, true)) {
                    throw new RuntimeException("Line {$number}: unknown kind [{$match[2]}].");
                }

                $t = count($milestones[$m]['todos']) - 1;
                $milestones[$m]['todos'][$t]['tasks'][] = ['title' => $match[3], 'description' => null, 'done' => $match[1] === 'x', 'kind' => $match[2]];
                $inTask = true;
                $context = 'task';

                continue;
            }

            if ($trimmed === '') {
                continue;
            }

            $text = trim($line);

            if ($inTask && str_starts_with($line, '  ')) {
                $t = count($milestones[$m]['todos']) - 1;
                $k = count($milestones[$m]['todos'][$t]['tasks']) - 1;
                $milestones[$m]['todos'][$t]['tasks'][$k]['description'] = self::append($milestones[$m]['todos'][$t]['tasks'][$k]['description'], $text);

                continue;
            }

            if ($context === 'milestone' && preg_match('/^Status: (geplant|veröffentlicht)$/u', $text) === 1) {
                continue;
            }

            if ($context === 'milestone') {
                $milestones[$m]['description'] = self::append($milestones[$m]['description'], $text);
            } elseif ($context === 'todo') {
                $t = count($milestones[$m]['todos']) - 1;
                $milestones[$m]['todos'][$t]['description'] = self::append($milestones[$m]['todos'][$t]['description'], $text);
            }
        }

        return ['milestones' => $milestones];
    }

    private static function append(?string $text, string $line): string
    {
        return $text === null ? $line : $text."\n".$line;
    }
}
