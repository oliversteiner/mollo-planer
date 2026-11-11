<?php

namespace Tests\Unit;

use App\Support\DevelopmentHistory\HistoryValidator;
use PHPUnit\Framework\TestCase;

class HistoryValidatorTest extends TestCase
{
    private function errors(string $markdown): array
    {
        return array_values(array_filter(HistoryValidator::validate($markdown), fn (array $i): bool => $i['severity'] === 'error'));
    }

    public function test_a_complete_entry_has_no_errors(): void
    {
        $md = "## Aufbau\n\n## v0.1 Start\n\n### Todo\n\n- [x] [feature] Etwas\n  Commit abc1234\n- [ ] [planning] Später\n";

        $this->assertSame([], $this->errors($md));
    }

    public function test_versions_must_increase(): void
    {
        $md = "## v0.2 B\n### Todo\n- [ ] [feature] Eintrag\n## v0.1 A\n### Todo\n- [ ] [feature] Anderer Eintrag\n";

        $this->assertStringContainsString('nicht größer', $this->errors($md)[0]['message']);
    }

    public function test_milestone_todo_and_task_need_content(): void
    {
        $messages = array_column($this->errors("## v0.1 Leer\n## v0.2 Auch leer\n### Todo\n"), 'message');

        $this->assertContains('Milestone ohne ToDo.', $messages);
        $this->assertContains('ToDo ohne Task.', $messages);
    }

    public function test_typos_and_unknown_kinds_are_reported_with_the_line(): void
    {
        $issues = HistoryValidator::validate("## v0.1 X\n### Todo\n- [X] [feature] Etwas Großes\n- [ ] [idee] Anderer Titel\n- [ ] Ohne Art\n");

        $this->assertSame([3, 4, 5], array_column($issues, 'line'));
        $this->assertStringContainsString('nicht lesbar', $issues[0]['message']);
        $this->assertStringContainsString('Unbekannte Art [idee]', $issues[1]['message']);
        $this->assertStringContainsString('nicht lesbar', $issues[2]['message']);
    }

    public function test_tasks_and_todos_must_sit_in_the_right_place(): void
    {
        $messages = array_column($this->errors("- [ ] [feature] Ohne Kontext\n### Ohne Version\n"), 'message');

        $this->assertContains('Task steht außerhalb eines ToDos.', $messages);
        $this->assertContains('ToDo steht außerhalb eines Milestones (erwartet „## vX.Y Titel“ davor).', $messages);
    }

    public function test_duplicate_titles_in_one_todo_are_refused_and_done_tasks_without_commit_warn(): void
    {
        $issues = HistoryValidator::validate("## v0.1 X\n### Todo\n- [ ] [feature] Gleich\n- [ ] [feature] Gleich\n- [x] [bug] Erledigt\n");

        $this->assertStringContainsString('Doppelter Task-Titel', implode(' ', array_column($issues, 'message')));
        $this->assertSame('warning', end($issues)['severity']);
    }

    public function test_the_real_history_file_is_valid(): void
    {
        $issues = HistoryValidator::validate((string) file_get_contents(dirname(__DIR__, 2).'/../docs/entwicklung.md'));

        $this->assertSame([], array_values(array_filter($issues, fn (array $i): bool => $i['severity'] === 'error')));
    }
}
