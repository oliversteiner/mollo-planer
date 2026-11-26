<?php

namespace Tests\Feature\Planer;

use App\Planer\Validator;
use Tests\TestCase;

/**
 * The messages of the file check are in the language of the request, with the
 * path as before (docs/fix-m4.md F7.3).
 */
class ValidatorMessagesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function document(): array
    {
        return json_decode((string) file_get_contents(base_path('../spec/fixtures/planer-valid.planer.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> path => message */
    private function messages(array $document): array
    {
        return array_column(Validator::validate($document), 'message', 'path');
    }

    public function test_every_kind_is_in_german(): void
    {
        app()->setLocale('de');
        $d = $this->document();
        $d['project']['description'] = str_repeat('x', 5001);
        $d['project']['name'] = 'X';
        $d['revision'] = -1;
        $d['project']['locale'] = 'fr';
        $d['project']['members'][0]['email'] = 'keine-adresse';
        $d['project']['members'][1]['role'] = 'Bar Team';
        $d['milestones'][0]['dueDate'] = '2026-13-40';

        $messages = $this->messages($d);

        $this->assertSame('Höchstens 5000 Zeichen (gefunden: 5001).', $messages['project.description']);
        $this->assertSame('Mindestens 2 Zeichen (gefunden: 1).', $messages['project.name']);
        $this->assertSame('Mindestens 0.', $messages['revision']);
        $this->assertSame('Ungültiger Wert.', $messages['project.locale']);
        $this->assertSame('Keine gültige E-Mail-Adresse.', $messages['project.members[0].email']);
        $this->assertSame('Falsches Format.', $messages['project.members[1].role']);
        $this->assertSame('Kein gültiges Datum (JJJJ-MM-TT).', $messages['milestones[0].dueDate']);
    }

    public function test_a_missing_and_an_unknown_field_are_in_german(): void
    {
        app()->setLocale('de');
        $d = $this->document();
        unset($d['milestones'][0]['todos'][0]['title']);
        $this->assertSame('Pflichtfeld fehlt.', $this->messages($d)['milestones[0].todos[0].title']);

        $d = $this->document();
        $d['project']['extra'] = true;
        $this->assertSame('Unbekanntes Feld.', $this->messages($d)['project.extra']);
    }

    public function test_an_unknown_field_that_looks_like_a_known_one_names_it(): void
    {
        app()->setLocale('de');
        $d = $this->document();
        $d['milestones'][0]['todos'][0]['tasks'][0]['assignee'] = 'lindita@example.ch';
        $this->assertSame('`assignee` gibt es nicht, gemeint ist wohl `assigneeEmail`.', $this->messages($d)['milestones[0].todos[0].tasks[0].assignee']);

        app()->setLocale('en');
        $this->assertSame('`assignee` is not a field, probably `assigneeEmail` is meant.', $this->messages($d)['milestones[0].todos[0].tasks[0].assignee']);

        $d = $this->document();
        $d['milestones'][0]['todos'][0]['tasks'][0]['userstory'] = ['role' => 'a', 'goal' => 'b', 'benefit' => 'c'];
        $this->assertSame('`userstory` is not a field, probably `userStory` is meant.', $this->messages($d)['milestones[0].todos[0].tasks[0].userstory']);
    }

    public function test_the_wrong_format_of_an_id_names_the_pattern(): void
    {
        app()->setLocale('de');
        $d = $this->document();
        $d['milestones'][0]['todos'][0]['tasks'][0]['id'] = 'k-new-47a';
        $d['milestones'][0]['todos'][0]['id'] = 't-abc';
        $d['milestones'][0]['id'] = 'm-x';
        $messages = $this->messages($d);

        $this->assertSame('Falsches Format. Erwartet: `k-<Zahl>` oder `k-new-<Zahl>`.', $messages['milestones[0].todos[0].tasks[0].id']);
        $this->assertSame('Falsches Format. Erwartet: `t-<Zahl>` oder `t-new-<Zahl>`.', $messages['milestones[0].todos[0].id']);
        $this->assertSame('Falsches Format. Erwartet: `m-<Zahl>` oder `m-new-<Zahl>`.', $messages['milestones[0].id']);

        app()->setLocale('en');
        $this->assertSame('Wrong format. Expected: `k-<number>` or `k-new-<number>`.', $this->messages($d)['milestones[0].todos[0].tasks[0].id']);
    }

    public function test_the_rules_of_the_file_are_in_german_and_english(): void
    {
        $d = $this->document();
        $d['milestones'][1]['todos'][0]['tasks'][0]['assigneeEmail'] = 'fremd@example.org';
        $d['milestones'][1]['todos'][0]['tasks'][0]['labels'] = ['gibt-es-nicht'];
        $d['milestones'][1]['id'] = $d['milestones'][0]['id'];

        app()->setLocale('de');
        $de = $this->messages($d);
        app()->setLocale('en');
        $en = $this->messages($d);

        $this->assertSame('fremd@example.org ist kein Mitglied des Projekts.', $de['milestones[1].todos[0].tasks[0].assigneeEmail']);
        $this->assertSame('gibt-es-nicht ist kein Label des Projekts.', $de['milestones[1].todos[0].tasks[0].labels[0]']);
        $this->assertStringContainsString('kommt doppelt vor', $de['milestones[1].id']);
        $this->assertSame('fremd@example.org is not a member of the project.', $en['milestones[1].todos[0].tasks[0].assigneeEmail']);
        $this->assertStringContainsString('is used twice', $en['milestones[1].id']);
    }

    public function test_release_rules_are_in_german(): void
    {
        app()->setLocale('de');
        $d = $this->document();
        $d['milestones'][0]['todos'][0]['tasks'][0]['commit'] = null;
        $d['milestones'][0]['todos'][0]['tasks'][1]['status'] = 'open';

        $messages = $this->messages($d);

        $this->assertStringContainsString('braucht einen Hash', $messages['milestones[0].todos[0].tasks[0].commit']);
        $this->assertStringContainsString('ist veröffentlicht, hat aber 1 nicht erledigte Tasks', $messages['milestones[0]']);
    }
}
