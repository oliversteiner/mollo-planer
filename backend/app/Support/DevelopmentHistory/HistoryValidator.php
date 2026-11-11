<?php

namespace App\Support\DevelopmentHistory;

/**
 * Checks docs/entwicklung.md line by line and reports every problem with its
 * line number. Errors break the seed, warnings do not.
 */
final class HistoryValidator
{
    private const TITLE_MAX = 160;

    /**
     * @return list<array{line: int, severity: 'error'|'warning', message: string}>
     */
    public static function validate(string $markdown): array
    {
        $issues = [];
        $lines = preg_split('/\R/', $markdown) ?: [];

        $versionLine = null;
        $previous = null;
        $milestoneLine = null;
        $todosInMilestone = 0;
        $todoLine = null;
        $tasksInTodo = 0;
        $titlesInTodo = [];
        $inVersion = false;
        $inTodo = false;
        $pendingDoneTask = null;
        $pendingCommit = false;

        $close = function () use (&$issues, &$todosInMilestone, &$milestoneLine, &$tasksInTodo, &$todoLine, &$pendingDoneTask, &$pendingCommit): void {
            if ($pendingDoneTask !== null && ! $pendingCommit) {
                $issues[] = ['line' => $pendingDoneTask['line'], 'severity' => 'warning', 'message' => 'Erledigter Task ohne „Commit …“ darunter.'];
            }
            $pendingDoneTask = null;
            $pendingCommit = false;
        };

        foreach ($lines as $index => $raw) {
            $number = $index + 1;
            $line = rtrim($raw);

            if (str_starts_with($line, '  Commit ') && $pendingDoneTask !== null) {
                $pendingCommit = preg_match('/^  Commit [0-9a-f]{7,40}$/', $line) === 1;
                if (! $pendingCommit) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Commit-Zeile muss „  Commit <Hash>“ sein.'];
                }

                continue;
            }

            if (str_starts_with($line, '## ')) {
                $close();
                if ($milestoneLine !== null && $todosInMilestone === 0) {
                    $issues[] = ['line' => $milestoneLine, 'severity' => 'error', 'message' => 'Milestone ohne ToDo.'];
                }

                if (preg_match('/^## v(\d+)\.(\d+)(?:\.(\d+))?\s+(.+)$/', $line, $m) !== 1) {
                    $inVersion = false;
                    $inTodo = false;
                    $milestoneLine = null;
                    if ($line !== '## Aufbau') {
                        $issues[] = ['line' => $number, 'severity' => 'warning', 'message' => 'Abschnitt ist kein Milestone (erwartet „## vX.Y Titel“) und wird übersprungen.'];
                    }

                    continue;
                }

                $version = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
                if ($previous !== null && $version <= $previous) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Version '.implode('.', $version).' ist nicht größer als die vorherige ('.implode('.', $previous).').'];
                }
                if (mb_strlen(trim($m[4])) > self::TITLE_MAX) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Titel des Milestones ist länger als '.self::TITLE_MAX.' Zeichen.'];
                }

                $previous = $version;
                $versionLine = $number;
                $milestoneLine = $number;
                $todosInMilestone = 0;
                $inVersion = true;
                $inTodo = false;

                continue;
            }

            if (str_starts_with($line, '### ')) {
                $close();
                if (! $inVersion) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'ToDo steht außerhalb eines Milestones (erwartet „## vX.Y Titel“ davor).'];

                    continue;
                }
                if ($inTodo && $tasksInTodo === 0) {
                    $issues[] = ['line' => $todoLine, 'severity' => 'error', 'message' => 'ToDo ohne Task.'];
                }

                $title = trim(substr($line, 4));
                if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'ToDo-Titel fehlt oder ist zu lang.'];
                }

                $todosInMilestone++;
                $todoLine = $number;
                $tasksInTodo = 0;
                $titlesInTodo = [];
                $inTodo = true;

                continue;
            }

            if (str_starts_with(ltrim($line), '- [')) {
                $close();
                if (preg_match('/^- \[( |x)\] \[(\w+)\] (.+)$/', $line, $m) !== 1) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Task nicht lesbar. Erwartet: „- [x] [art] Titel“ oder „- [ ] [art] Titel“.'];

                    continue;
                }
                if (! $inTodo) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Task steht außerhalb eines ToDos.'];

                    continue;
                }
                if (! in_array($m[2], HistoryMarkdown::KINDS, true)) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => "Unbekannte Art [{$m[2]}]. Erlaubt: ".implode(', ', HistoryMarkdown::KINDS).'.'];
                }
                $title = trim($m[3]);
                if (mb_strlen($title) < 2 || mb_strlen($title) > self::TITLE_MAX) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => 'Task-Titel muss 2 bis '.self::TITLE_MAX.' Zeichen lang sein.'];
                }
                if (in_array($title, $titlesInTodo, true)) {
                    $issues[] = ['line' => $number, 'severity' => 'error', 'message' => "Doppelter Task-Titel im ToDo: „{$title}“."];
                }
                $titlesInTodo[] = $title;

                $tasksInTodo++;
                if ($m[1] === 'x') {
                    $pendingDoneTask = ['line' => $number];
                    $pendingCommit = false;
                }

                continue;
            }

            if (trim($line) === '') {
                continue;
            }
        }

        $close();
        if ($milestoneLine !== null && $todosInMilestone === 0) {
            $issues[] = ['line' => $milestoneLine, 'severity' => 'error', 'message' => 'Milestone ohne ToDo.'];
        }
        if ($inTodo && $tasksInTodo === 0 && $todoLine !== null) {
            $issues[] = ['line' => $todoLine, 'severity' => 'error', 'message' => 'ToDo ohne Task.'];
        }
        if ($previous === null) {
            $issues[] = ['line' => 1, 'severity' => 'error', 'message' => 'Kein Milestone gefunden.'];
        }

        usort($issues, fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $issues;
    }
}
