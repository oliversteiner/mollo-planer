import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { validatePlaner } from '../core/validate-planer.mjs';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const SOURCE = fileURLToPath(new URL('../../../spec/fixtures/beispiel-planer.planer.json', import.meta.url));

let dir;
let file;

beforeEach(() => {
  dir = mkdtempSync(join(tmpdir(), 'mollo-helpers-'));
  mkdirSync(join(dir, 'docs'));
  file = join(dir, 'docs', 'demo.planer.json');
  copyFileSync(SOURCE, file);
});

afterEach(() => {
  rmSync(dir, { recursive: true, force: true });
});

/** Ruft den CLI auf. Der Befehl läuft im Testordner, damit docs/*.planer.json gefunden wird. */
function run(args, stdin = '') {
  return spawnSync(process.execPath, [cli, ...args], { cwd: dir, encoding: 'utf8', input: stdin });
}

const text = () => readFileSync(file, 'utf8');
const plan = () => JSON.parse(text());

test('revision: liefert die aktuelle Revision, ändert nichts', () => {
  const before = text();
  const result = run(['revision']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, '3\n');
  assert.equal(text(), before);
});

test('list: eine Zeile je Element, ohne Beschreibungen', () => {
  const result = run(['list']);
  assert.equal(result.status, 0);
  assert.match(result.stdout, /^m-001 v1\.3 \[released\] Ordering-Grundlage$/m);
  assert.match(result.stdout, /^ {4}k-004 \[~\] Ordering-Service ausbauen$/m);
  assert.doesNotMatch(result.stdout, /Ziel ist eine Klasse/);
});

test('show: gibt das Element mit seinen Kindern aus', () => {
  const result = run(['show', 't-001']);
  assert.equal(result.status, 0);
  const node = JSON.parse(result.stdout);
  assert.equal(node.id, 't-001');
  assert.equal(node.tasks.length, 2);
});

test('show: unbekannte ID endet mit Code 1', () => {
  const result = run(['show', 'k-999']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /nicht gefunden/);
});

test('add task: hängt ans Ende des ToDos, vergibt die nächste ID, setzt changed', () => {
  const result = run(['add', 'task', '--todo', 't-003', '--title', 'Neue Aufgabe']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, 'k-008 angelegt\n');

  const todo = plan().milestones[1].todos[0];
  assert.deepEqual(todo.tasks.at(-1), { id: 'k-008', title: 'Neue Aufgabe', status: 'open' });
  assert.equal(plan().revision, 3);
  assert.equal(plan().changed, true);
  assert.deepEqual(validatePlaner(text()).filter((issue) => issue.severity === 'error'), []);
});

test('add todo: vergibt t-005 im Milestone', () => {
  const result = run(['add', 'todo', '--milestone', 'm-002', '--title', 'Test-ToDo']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, 't-005 angelegt\n');
  assert.equal(plan().milestones[1].todos.at(-1).id, 't-005');
});

test('add milestone: Version mit Standard-Status planned, ID m-003', () => {
  const result = run(['add', 'milestone', '--title', 'Neu', '--version', '1.5']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, 'm-003 angelegt\n');
  assert.deepEqual(plan().milestones.at(-1).release, { version: '1.5', status: 'planned' });
});

test('add task: Labels, Zuständiger und Commit aus Flags', () => {
  const result = run(['add', 'task', '--todo', 't-003', '--title', 'Mit Feldern', '--assignee', 'oliver@mollo.ch', '--label', 'backend', '--label', 'bug', '--due', '2026-12-20', '--status', 'review']);
  assert.equal(result.status, 0, result.stderr);
  const task = plan().milestones[1].todos[0].tasks.at(-1);
  assert.deepEqual(task.labels, ['backend', 'bug']);
  assert.equal(task.assigneeEmail, 'oliver@mollo.ch');
  assert.equal(task.dueDate, '2026-12-20');
  assert.equal(task.status, 'review');
});

test('add task --json: Beschreibung und userStory von stdin', () => {
  const element = {
    title: 'Aus JSON',
    description: 'Langer Text\n\n- Punkt',
    userStory: { role: 'Mitglied', goal: 'Filter nutzen', benefit: 'schneller arbeiten' },
  };
  const result = run(['add', 'task', '--todo', 't-003', '--json'], JSON.stringify(element));
  assert.equal(result.status, 0, result.stderr);
  const task = plan().milestones[1].todos[0].tasks.at(-1);
  assert.equal(task.id, 'k-008');
  assert.equal(task.status, 'open');
  assert.deepEqual(task.userStory, element.userStory);
});

test('add --json: eine vorgegebene id wird abgelehnt', () => {
  const result = run(['add', 'task', '--todo', 't-001', '--json'], JSON.stringify({ id: 'k-050', title: 'Falsch' }));
  assert.equal(result.status, 2);
  assert.match(result.stderr, /vergibt der Helfer/);
});

test('add: falsches Elternteil endet mit Code 1 und ändert nichts', () => {
  const before = text();
  const result = run(['add', 'task', '--todo', 'm-001', '--title', 'Falsch']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /kein ToDo/);
  assert.equal(text(), before);
});

test('add: ohne --title endet mit Code 2', () => {
  assert.equal(run(['add', 'task', '--todo', 't-001']).status, 2);
});

test('add: ungültiger Wert wird vom Validator abgelehnt, Datei bleibt unverändert', () => {
  const before = text();
  const result = run(['add', 'task', '--todo', 't-003', '--title', 'Gültiger Titel', '--assignee', 'nobody@example.org']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Änderung abgelehnt, Datei unverändert/);
  assert.match(result.stderr, /kein Mitglied/);
  assert.equal(text(), before);
});

test('status: setzt den Status und die Revision', () => {
  const result = run(['status', 'k-004', 'review']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, 'k-004: in_progress → review\n');
  assert.equal(plan().milestones[1].todos[0].tasks[0].status, 'review');
});

test('add mit done ohne Commit in veröffentlichter Version wird abgelehnt', () => {
  // m-001 ist veröffentlicht. Ein erledigter Task ohne Commit darf dort nicht entstehen.
  const before = text();
  const result = run(['add', 'task', '--todo', 't-002', '--title', 'Ohne Commit', '--status', 'done']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Änderung abgelehnt, Datei unverändert/);
  assert.match(result.stderr, /Hash oder „none“/);
  assert.equal(text(), before);
});

test('add mit offenem Task in veröffentlichter Version wird abgelehnt', () => {
  const before = text();
  const result = run(['add', 'task', '--todo', 't-002', '--title', 'Offen im Release']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /veröffentlicht, hat aber/);
  assert.equal(text(), before);
});

test('status done mit Commit in veröffentlichter Version wird angenommen', () => {
  // k-004 wird in einer geplanten Version erledigt, in m-001 dagegen darf nichts offen sein.
  const result = run(['status', 'k-004', 'done', '--commit', '1234abc']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(plan().milestones[1].todos[0].tasks[0].commit, '1234abc');
});

test('status: „none“ als Commit ist nur bei done erlaubt', () => {
  const before = text();
  const result = run(['status', 'k-004', 'open', '--commit', 'none']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /„none“ ist nur bei erledigten Tasks erlaubt/);
  assert.equal(text(), before);
});

test('status: Milestone-ID endet mit Code 2', () => {
  assert.equal(run(['status', 'm-002', 'done']).status, 2);
});

test('status: unbekannter Status endet mit Code 2', () => {
  assert.equal(run(['status', 'k-004', 'doing']).status, 2);
});

test('move: Task in ein anderes ToDo derselben Version, ID bleibt, Revision steigt', () => {
  const result = run(['move', 'k-007', '--to', 't-003', '--position', '0']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, 'k-007 verschoben\n');
  const todo = plan().milestones[1].todos[0];
  assert.equal(todo.tasks[0].id, 'k-007');
  assert.equal(plan().milestones[1].todos[1].tasks.length, 1);
});

test('move: Milestone an die erste Stelle', () => {
  const result = run(['move', 'm-002', '--position', '0']);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(plan().milestones[0].id, 'm-002');
});

test('move: Position außerhalb der Liste wird abgelehnt', () => {
  const before = text();
  assert.equal(run(['move', 'k-007', '--to', 't-003', '--position', '9']).status, 2);
  assert.equal(text(), before);
});

test('move: unbekannte ID endet mit Code 1', () => {
  assert.equal(run(['move', 'k-999', '--to', 't-001']).status, 1);
});

test('move: Milestone mit --to endet mit Code 2', () => {
  assert.equal(run(['move', 'm-002', '--to', 'm-001']).status, 2);
});

test('Lesebefehle ändern die Revision nicht und setzen kein changed', () => {
  run(['list']);
  run(['show', 'k-001']);
  run(['revision']);
  assert.equal(plan().revision, 3);
  assert.equal(plan().changed, undefined);
});

test('Ungültige Ausgangsdatei: kein Helfer schreibt', () => {
  const broken = JSON.parse(text());
  broken.milestones[0].todos[0].tasks[0].status = 'doing';
  writeFileSync(file, `${JSON.stringify(broken, null, 2)}\n`);
  const before = text();
  const result = run(['add', 'task', '--todo', 't-001', '--title', 'X']);
  assert.equal(result.status, 1);
  assert.match(result.stderr, /ist ungültig/);
  assert.equal(text(), before);
});

test('Unbekannte Option endet mit Code 2', () => {
  assert.equal(run(['add', 'task', '--todo', 't-001', '--title', 'X', '--foo', 'bar']).status, 2);
});

test('Ohne Datei und ohne docs/*.planer.json: Code 2', () => {
  const other = mkdtempSync(join(tmpdir(), 'mollo-empty-'));
  try {
    const result = spawnSync(process.execPath, [cli, 'revision'], { cwd: other, encoding: 'utf8' });
    assert.equal(result.status, 2);
    assert.match(result.stderr, /--datei/);
  } finally {
    rmSync(other, { recursive: true, force: true });
  }
});

test('add task: --label nimmt den Namen oder den Schlüssel aus den Labels des Projekts', () => {
  const byName = run(['add', 'task', '--todo', 't-003', '--title', 'Mit Namen', '--label', 'Fehler']);
  assert.equal(byName.status, 0, byName.stderr);
  assert.deepEqual(plan().milestones[1].todos[0].tasks.at(-1).labels, ['bug']);
});

test('add task: unbekanntes Label endet mit Code 2 und ändert nichts', () => {
  const before = text();
  const result = run(['add', 'task', '--todo', 't-003', '--title', 'Falsch', '--label', 'Nicht da']);
  assert.equal(result.status, 2);
  assert.match(result.stderr, /„Nicht da“ gibt es im Projekt nicht/);
  assert.equal(text(), before);
});

test('status: idea und planned sind gültige Status, Freigabe-Werte planned und released', () => {
  const idea = run(['status', 'k-004', 'idea']);
  assert.equal(idea.status, 0, idea.stderr);
  assert.equal(plan().milestones[1].todos[0].tasks[0].status, 'idea');
  const planned = run(['status', 'k-004', 'planned']);
  assert.equal(planned.status, 0, planned.stderr);
  assert.equal(plan().milestones[1].todos[0].tasks[0].status, 'planned');
  const old = run(['add', 'milestone', '--title', 'Alt', '--version', '2.0', '--release-status', 'veröffentlicht']);
  assert.equal(old.status, 2);
});
