<?php

namespace Tests\Unit;

use App\Support\DevelopmentHistory\HistoryMarkdown;
use PHPUnit\Framework\TestCase;

class HistoryMarkdownTest extends TestCase
{
    private const SAMPLE = <<<'MD'
# Entwicklung

## Aufbau

- **Milestone** ist eine Version.

## v0.1 Backend

Erste Zeile zur Version.

### Datenmodell

Kontext des ToDos.

- [x] [feature] Migrationen anlegen
  Commit abc1234
- [ ] [bug] Rechte prüfen
  Zweite Zeile der Beschreibung.

## v0.2 Frontend

### Einrichtung

- [x] [planning] Plan schreiben
MD;

    public function test_reads_milestones_todos_and_tasks_with_their_kind_and_state(): void
    {
        $history = HistoryMarkdown::parse(self::SAMPLE);

        $this->assertCount(2, $history['milestones']);
        $this->assertSame('v0.1 Backend', $history['milestones'][0]['title']);
        $this->assertSame('Erste Zeile zur Version.', $history['milestones'][0]['description']);
        $this->assertSame('Datenmodell', $history['milestones'][0]['todos'][0]['title']);
        $this->assertSame('Kontext des ToDos.', $history['milestones'][0]['todos'][0]['description']);

        $tasks = $history['milestones'][0]['todos'][0]['tasks'];
        $this->assertSame(['Migrationen anlegen', 'Rechte prüfen'], array_column($tasks, 'title'));
        $this->assertSame(['feature', 'bug'], array_column($tasks, 'kind'));
        $this->assertSame([true, false], array_column($tasks, 'done'));
        $this->assertSame('Commit abc1234', $tasks[0]['description']);
        $this->assertSame('Zweite Zeile der Beschreibung.', $tasks[1]['description']);
    }

    public function test_sections_that_are_not_versions_are_skipped(): void
    {
        $titles = array_column(HistoryMarkdown::parse(self::SAMPLE)['milestones'], 'title');

        $this->assertNotContains('Aufbau', $titles);
        $this->assertSame(['v0.1 Backend', 'v0.2 Frontend'], $titles);
    }

    public function test_release_status_line_under_a_version_is_not_its_description(): void
    {
        $history = HistoryMarkdown::parse("## v0.1 X\nStatus: veröffentlicht\n\nText.\n### Todo\n- [x] [feature] Fertig\n  Commit abc1234\n");

        $this->assertSame('Text.', $history['milestones'][0]['description']);
    }

    public function test_blocked_is_a_valid_kind(): void
    {
        $history = HistoryMarkdown::parse("## v0.1 X\n### Todo\n- [ ] [blocked] Wartet auf Freigabe\n");

        $this->assertSame('blocked', $history['milestones'][0]['todos'][0]['tasks'][0]['kind']);
    }

    public function test_an_unknown_kind_is_refused_with_the_line_number(): void
    {
        $this->expectExceptionMessage('Line 4: unknown kind [idee].');

        HistoryMarkdown::parse("## v0.1 X\n### T\n\n- [ ] [idee] Etwas\n");
    }

    public function test_a_task_without_a_todo_is_refused(): void
    {
        $this->expectExceptionMessage('task without a todo');

        HistoryMarkdown::parse("## v0.1 X\n- [ ] [feature] Etwas\n");
    }
}
