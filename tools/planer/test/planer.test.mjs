import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { validatePlaner } from '../core/validate-planer.mjs';
import { validateAgainst } from '../core/json-schema.mjs';
import { forbiddenMarkdown } from '../core/validate-planer.mjs';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const read = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');
const fixture = (name) => read(`../../../spec/fixtures/${name}`);
const run = (args, cwd) => spawnSync(process.execPath, [cli, ...args], { encoding: 'utf8', cwd });

const errors = (text) => validatePlaner(text).filter((issue) => issue.severity === 'error');
const warnings = (text) => validatePlaner(text).filter((issue) => issue.severity === 'warning');

/** Ein gültiges Dokument als Objekt, zum gezielten Verändern. */
function baseDocument() {
  return JSON.parse(fixture('planer-valid.planer.json'));
}

/** Prüft ein verändertes Dokument und gibt die Fehlerpfade zurück. */
function errorPaths(mutate) {
  const document = baseDocument();
  mutate(document);
  return errors(JSON.stringify(document, null, 2) + '\n').map((issue) => issue.path);
}

test('gültige Fixture: keine Fehler, keine Hinweise', () => {
  assert.deepEqual(validatePlaner(fixture('planer-valid.planer.json')), []);
});

test('ungültige Fixture: genau die fünf erwarteten Fehler, mit Pfad', () => {
  const issues = validatePlaner(fixture('planer-invalid.planer.json'));
  assert.deepEqual(
    issues.map((issue) => [issue.severity, issue.path]),
    [
      ['error', 'milestones[0].todos[0].tasks[0].commit'],
      ['error', 'milestones[0]'],
      ['error', 'milestones[1].todos[0].tasks[0].commit'],
      ['error', 'milestones[1].todos[0].tasks[1].id'],
      ['error', 'milestones[1].todos[0].tasks[2].assigneeEmail'],
    ],
  );
});

test('Beispiel aus spec/format-v2.md ist gültig', () => {
  const markdown = read('../../../spec/format-v2.md');
  const block = markdown.match(/```json\n([\s\S]*?)\n```/)[1];
  assert.deepEqual(errors(block), []);
});

test('changed: nur true ist erlaubt, das Feld fehlt bei unveränderten Dateien', () => {
  assert.deepEqual(errorPaths((d) => { d.changed = false; }), ['changed']);
  assert.deepEqual(errorPaths((d) => { d.changed = true; }), []);
});

test('Schema: falsches id-Format, Rollen-Platzhalter, berechneter Wert', () => {
  assert.deepEqual(errorPaths((d) => { d.milestones[0].todos[0].tasks[0].id = 'k-abc'; }), ['milestones[0].todos[0].tasks[0].id']);
  assert.deepEqual(errorPaths((d) => { d.project.members[0].role = '<ProjectRole>'; }), ['project.members[0].role']);
  assert.deepEqual(errorPaths((d) => { d.milestones[1].status = 'open'; }), ['milestones[1].status']);
});

test('Schema: userStory ohne benefit wird abgelehnt', () => {
  const paths = errorPaths((d) => {
    d.milestones[1].todos[0].tasks[1].userStory = { role: 'Mitglied', goal: 'x' };
  });
  assert.deepEqual(paths, ['milestones[1].todos[0].tasks[1].userStory.benefit']);
});

test('Schema: Grenzen (Titel, Version, Hash, Label, Datum)', () => {
  assert.deepEqual(errorPaths((d) => { d.milestones[0].todos[0].tasks[0].title = 'x'; }), ['milestones[0].todos[0].tasks[0].title']);
  assert.deepEqual(errorPaths((d) => { d.milestones[0].release.version = '1.4.0'; }), ['milestones[0].release.version']);
  assert.deepEqual(errorPaths((d) => { d.milestones[1].todos[0].tasks[0].commit = 'nein'; }), ['milestones[1].todos[0].tasks[0].commit']);
  assert.deepEqual(errorPaths((d) => { d.milestones[1].todos[0].tasks[0].labels = ['x'.repeat(41)]; }), ['milestones[1].todos[0].tasks[0].labels[0]']);
  assert.deepEqual(errorPaths((d) => { d.milestones[0].dueDate = '31.12.2026'; }), ['milestones[0].dueDate']);
});

test('Schema: unbekanntes Feld und fehlende Pflichtfelder', () => {
  assert.deepEqual(errorPaths((d) => { d.milestones[0].todos[0].tasks[0].position = 1; }), ['milestones[0].todos[0].tasks[0].position']);
  assert.deepEqual(errorPaths((d) => { delete d.revision; }), ['revision']);
});

test('Schema-Fehler verhindern die Semantikprüfung', () => {
  // Ein Dokument mit doppelter ID und Schemafehler meldet nur den Schemafehler.
  const paths = errorPaths((d) => {
    d.milestones[1].todos[0].tasks[1].id = 'k-002';
    d.milestones[1].todos[0].tasks[0].status = 'doing';
  });
  assert.deepEqual(paths, ['milestones[1].todos[0].tasks[0].status']);
});

test('Semantik: Commit „none“ nur bei erledigten Tasks', () => {
  const paths = errorPaths((d) => { d.milestones[1].todos[0].tasks[0].commit = 'none'; });
  assert.deepEqual(paths, ['milestones[1].todos[0].tasks[0].commit']);
});

test('Semantik: erledigter Task ohne Commit ist in geplanter Version erlaubt, in veröffentlichter nicht', () => {
  assert.deepEqual(errorPaths((d) => { d.milestones[0].release.status = 'planned'; d.milestones[0].todos[0].tasks[0].commit = null; }), []);
  assert.deepEqual(errorPaths((d) => { d.milestones[0].todos[0].tasks[0].commit = null; }), ['milestones[0].todos[0].tasks[0].commit']);
});

test('Semantik: fehlendes commit-Feld zählt wie null', () => {
  const data = baseDocument();
  delete data.milestones[0].todos[0].tasks[0].commit;
  const issues = errors(JSON.stringify(data, null, 2) + '\n');
  assert.deepEqual(issues.map((issue) => issue.path), ['milestones[0].todos[0].tasks[0].commit']);
});

test('Semantik: Mitglied als Zuständiger, Groß- und Kleinschreibung egal', () => {
  assert.deepEqual(errorPaths((d) => { d.milestones[1].todos[0].tasks[0].assigneeEmail = 'ANNA@example.org'; }), []);
});

test('Hinweis, wenn die Formatierung nicht kanonisch ist, aber kein Fehler', () => {
  const compact = JSON.stringify(baseDocument());
  assert.deepEqual(errors(compact), []);
  assert.equal(warnings(compact).length, 1);
});

test('Whitelist: HTML, Bilder, Überschrift Ebene 1 und Code-Blöcke sind Fehler, Ebene 2 ist erlaubt', () => {
  const at = (description) => {
    const data = baseDocument();
    data.milestones[1].todos[0].tasks[0].description = description;
    return errors(JSON.stringify(data, null, 2) + '\n').map((issue) => issue.path);
  };
  const path = 'milestones[1].todos[0].tasks[0].description';
  assert.deepEqual(at('Text <b>fett</b>'), [path]);
  assert.deepEqual(at('![Bild](https://x.de/a.png)'), [path]);
  assert.deepEqual(at('# Titel'), [path]);
  assert.deepEqual(at('```\ncode\n```'), [path]);
  assert.deepEqual(at('[x](javascript:alert(1))'), [path]);
  assert.deepEqual(at('## Abschnitt\n\n[Link](https://x.de) und [Mail](mailto:a@b.ch)'), []);
});

test('Inline-Code ist erlaubt', () => {
  assert.deepEqual(forbiddenMarkdown('Die Klasse `Ordering` mit **fett**'), []);
});

test('kein gültiges JSON: Fehler ohne Pfad', () => {
  const issues = validatePlaner('{ kaputt');
  assert.equal(issues.length, 1);
  assert.equal(issues[0].severity, 'error');
  assert.match(issues[0].message, /Keine gültige JSON-Datei/);
});

test('Schlüsselwort, das der Prüfer nicht kennt, bricht ab statt still zu prüfen', () => {
  assert.throws(() => validateAgainst({ type: 'string', oneOf: [] }, 'x'), /Nicht unterstütztes Schlüsselwort „oneOf“/);
});

test('CLI validate: gültige Planer-Datei endet mit Code 0', () => {
  const result = run(['validate', fileFixture('planer-valid.planer.json')]);
  assert.equal(result.status, 0);
  assert.match(result.stdout, /Keine Fehler/);
});

test('CLI validate: ungültige Planer-Datei endet mit Code 1 und nennt Pfade', () => {
  const result = run(['validate', fileFixture('planer-invalid.planer.json')]);
  assert.equal(result.status, 1);
  assert.match(result.stdout, /planer-invalid\.planer\.json:milestones\[0\]\.todos\[0\]\.tasks\[0\]\.commit {2}FEHLER/);
  assert.match(result.stdout, /5 Fehler/);
});

test('CLI validate ohne Argument findet docs/*.planer.json', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-planer-'));
  try {
    mkdirSync(join(dir, 'docs'));
    writeFileSync(join(dir, 'docs', 'demo.planer.json'), fixture('planer-valid.planer.json'));
    const result = run(['validate'], dir);
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /Keine Fehler/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

function fileFixture(name) {
  return fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url));
}

test('Labels: Schlüssel und Namen sind je Projekt eindeutig, Tasks verweisen nur auf vorhandene', () => {
  assert.deepEqual(errorPaths((d) => { d.project.labels.push({ key: 'backend', names: { de: 'Doppelt', en: 'Twice' }, color: 'red' }); }), ['project.labels[1].key']);
  assert.deepEqual(errorPaths((d) => { d.project.labels.push({ key: 'neu', names: { de: 'Backend', en: 'Twice' }, color: 'red' }); }), ['project.labels[1].names.de']);
  assert.deepEqual(errorPaths((d) => { d.milestones[0].todos[0].tasks[0].labels = ['gibt-es-nicht']; }), ['milestones[0].todos[0].tasks[0].labels[0]']);
});
