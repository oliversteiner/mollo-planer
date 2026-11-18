import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { parse } from '../core/parse.mjs';

const fixture = (name) => readFileSync(fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url)), 'utf8');

test('Milestones, ToDos und Tasks werden mit Beschreibung, Art und Commit gelesen', () => {
  const { milestones } = parse(fixture('valid.md'));
  assert.deepEqual(milestones.map((m) => m.number), ['1.0', '1.1']);

  const first = milestones[0];
  assert.equal(first.title, 'Grundlage');
  assert.equal(first.description, 'Erste Beschreibung des Milestones.\nZweite Zeile derselben Beschreibung.');

  const todo = first.todos[0];
  assert.equal(todo.title, 'Datenmodell');
  assert.equal(todo.description, 'Beschreibung des ToDos.');
  assert.equal(todo.tasks.length, 3);

  assert.deepEqual(todo.tasks[0], {
    title: 'Add the data model',
    description: 'Zweite Zeile der Task-Beschreibung.',
    done: true,
    kind: 'feature',
    commit: 'abc1234',
  });
  assert.equal(todo.tasks[1].done, false);
  assert.equal(todo.tasks[1].commit, null);
  assert.equal(todo.tasks[2].kind, 'bug');
  assert.equal(todo.tasks[2].commit, '9f8e7d6c');
});

test('Abschnitte ohne Milestone-Überschrift werden übersprungen', () => {
  const { milestones } = parse(fixture('valid.md'));
  assert.equal(milestones.length, 2);
  assert.equal(milestones[1].todos[0].tasks[0].kind, 'design');
});

test('Die Entwicklungsdoku von projektverwaltung hat acht Milestones', () => {
  const { milestones } = parse(fixture('projektverwaltung-entwicklung.md'));
  assert.deepEqual(milestones.map((m) => m.number), ['0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8']);
});

test('Status wird gelesen, Standard ist geplant, die Zeile gehört nicht zur Beschreibung', () => {
  const markdown = '## v1.0 A\nStatus: veröffentlicht\nBeschreibung\n\n### T\n\n- [x] [feature] X\n  Commit 1a2b3c4\n\n## v1.1 B\n\n### T\n\n- [ ] [feature] Y\n';
  const { milestones } = parse(markdown);
  assert.equal(milestones[0].status, 'veröffentlicht');
  assert.equal(milestones[0].description, 'Beschreibung');
  assert.equal(milestones[1].status, 'geplant');
});

test('Versionsnummer wird in Major und Minor zerlegt', () => {
  const { milestones } = parse('## v12.4 Titel\n\n### T\n\n- [ ] [feature] X\n');
  assert.equal(milestones[0].major, 12);
  assert.equal(milestones[0].minor, 4);
  assert.equal(milestones[0].number, '12.4');
});
