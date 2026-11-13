import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { validate } from '../core/validate.mjs';

const fixture = (name) => readFileSync(fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url)), 'utf8');
const errors = (markdown) => validate(markdown).filter((issue) => issue.severity === 'error');
const messages = (markdown) => errors(markdown).map((issue) => issue.message);

test('gültige Datei ohne Fehler', () => {
  assert.deepEqual(errors(fixture('valid.md')), []);
});

test('Aufbau und Einleitung sind erlaubt, andere Abschnitte geben einen Hinweis', () => {
  const markdown = '## Sonstiges\n\n## v1.0 A\n\n### T\n\n- [ ] [feature] Aufgabe\n';
  const issues = validate(markdown);
  assert.equal(issues.length, 1);
  assert.equal(issues[0].severity, 'warning');
  assert.equal(issues[0].line, 1);
});

test('Erledigter Task ohne Commit ist ein Fehler, offener Task nicht', () => {
  const done = '## v1.0 A\n\n### T\n\n- [x] [feature] Erledigt\n';
  assert.deepEqual(messages(done), ['Erledigter Task ohne „  Commit <Hash>“ darunter.']);

  const open = '## v1.0 A\n\n### T\n\n- [ ] [feature] Offen\n';
  assert.deepEqual(errors(open), []);
});

test('Commit mit Hash von 7 bis 40 Zeichen ist gültig, sonst Fehler', () => {
  const good = '## v1.0 A\n\n### T\n\n- [x] [bug] Fix\n  Commit 1a2b3c4\n';
  assert.deepEqual(errors(good), []);

  const bad = '## v1.0 A\n\n### T\n\n- [x] [bug] Fix\n  Commit XYZ\n';
  assert.ok(messages(bad).includes('Commit-Zeile muss „  Commit <Hash>“ sein.'));
});

test('Patch-Stufe ist nicht erlaubt', () => {
  assert.ok(messages('## v1.3.4 Patch\n\n### T\n\n- [ ] [feature] X\n').some((m) => m.startsWith('Patch-Stufe')));
});

test('Versionen müssen steigen', () => {
  const markdown = '## v1.2 B\n\n### T\n\n- [ ] [feature] X\n\n## v1.1 A\n\n### T\n\n- [ ] [feature] Y\n';
  assert.ok(messages(markdown).some((m) => m.startsWith('Version 1.1 ist nicht größer')));
});

test('Unbekannte Art, doppelter Task-Titel und ToDo/Milestone ohne Inhalt', () => {
  const markdown = [
    '## v1.0 A',
    '',
    '### Leer',
    '',
    '### Mit Task',
    '',
    '- [ ] [unbekannt] X',
    '- [ ] [feature] Doppelt',
    '- [ ] [feature] Doppelt',
    '',
    '## v1.1 Ohne ToDo',
    '',
  ].join('\n');
  const all = messages(markdown);
  assert.ok(all.includes('ToDo ohne Task.'));
  assert.ok(all.some((m) => m.startsWith('Unbekannte Art [unbekannt]')));
  assert.ok(all.includes('Doppelter Task-Titel im ToDo: „Doppelt“.'));
  assert.ok(all.includes('Milestone ohne ToDo.'));
});

test('Task außerhalb eines ToDos und ToDo außerhalb eines Milestones', () => {
  const all = messages('### Loser ToDo\n\n## v1.0 A\n\n- [ ] [feature] Task ohne ToDo\n');
  assert.ok(all.includes('ToDo steht außerhalb eines Milestones (erwartet „## vM.m Titel“ davor).'));
  assert.ok(all.includes('Task steht außerhalb eines ToDos.'));
});

test('Titel über 160 Zeichen sind ein Fehler', () => {
  const long = 'x'.repeat(161);
  assert.ok(messages(`## v1.0 ${long}\n\n### T\n\n- [ ] [feature] X\n`).includes('Titel des Milestones ist länger als 160 Zeichen.'));
});

test('Veröffentlichte Version mit offenem Task ist ein Fehler', () => {
  const markdown = '## v1.0 A\nStatus: veröffentlicht\n\n### T\n\n- [x] [feature] Fertig\n  Commit 1a2b3c4\n- [ ] [feature] Offen\n';
  assert.deepEqual(messages(markdown), ['Version 1.0 ist veröffentlicht, hat aber 1 offene Task(s).']);
});

test('Veröffentlichte Version mit nur erledigten Tasks ist gültig', () => {
  const markdown = '## v1.0 A\nStatus: veröffentlicht\n\n### T\n\n- [x] [feature] Fertig\n  Commit 1a2b3c4\n';
  assert.deepEqual(validate(markdown), []);
});

test('Geplante Version mit nur erledigten Tasks bekommt einen Hinweis', () => {
  const markdown = '## v1.0 A\n\n### T\n\n- [x] [feature] Fertig\n  Commit 1a2b3c4\n';
  const issues = validate(markdown);
  assert.equal(issues.length, 1);
  assert.equal(issues[0].severity, 'warning');
  assert.match(issues[0].message, /Status auf „veröffentlicht“/);
});

test('Unbekannter Status ist ein Fehler', () => {
  assert.ok(messages('## v1.0 A\nStatus: fertig\n\n### T\n\n- [ ] [feature] X\n').some((m) => m.startsWith('Unbekannter Status „fertig“')));
});

test('Status steht nur direkt unter der Überschrift', () => {
  const markdown = '## v1.0 A\n\nBeschreibung\nStatus: veröffentlicht\n\n### T\n\n- [ ] [feature] X\n';
  assert.ok(messages(markdown).includes('Status steht nur direkt unter der Milestone-Überschrift.'));
});

test('Datei ohne Milestone ist ungültig', () => {
  assert.ok(messages('# Nur Text\n').includes('Kein Milestone gefunden.'));
});

test('ungültige Datei: jede Regel meldet die Zeile', () => {
  const issues = errors(fixture('invalid.md'));
  assert.ok(issues.length >= 10);
  const lines = issues.map((issue) => issue.line);
  assert.deepEqual(lines, [...lines].sort((a, b) => a - b), 'Fehler sind nach Zeile sortiert');
});

test('Legacy: „Ohne Commit“ unter einem erledigten Task ist gültig', () => {
  const ok = '## v1.0 A\n\n### T\n\n- [x] [bug] Fix\n  Ohne Commit\n';
  assert.deepEqual(messages(ok), []);
});

test('Legacy: „Ohne Commit“ unter einem offenen Task ist ein Fehler', () => {
  const bad = '## v1.0 A\n\n### T\n\n- [ ] [bug] Fix\n  Ohne Commit\n';
  assert.deepEqual(messages(bad), ['„  Ohne Commit“ steht nur unter einem erledigten Task.']);
});
