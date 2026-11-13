import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { renderPlaner } from '../core/render-planer.mjs';
import { canonicalText } from '../core/planer-canonical.mjs';
import { planerFromMarkdown } from '../core/planer-from-md.mjs';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const read = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');
const fixture = (name) => read(`../../../spec/fixtures/${name}`);
const golden = (name) => read(`../../../spec/fixtures/golden/${name}`);
const run = (args) => spawnSync(process.execPath, [cli, ...args], { encoding: 'utf8' });
const fixturePath = (name) => fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url));

/** Ein minimaler gültiger Plan, zum gezielten Verändern. */
function plan(overrides = {}) {
  return {
    format: 'planer/2',
    revision: 1,
    project: { name: 'Demo', locale: 'de', members: [] },
    milestones: [
      {
        id: 'm-001',
        title: 'Start',
        todos: [{ id: 't-001', title: 'Arbeit', tasks: [{ id: 'k-001', title: 'Aufgabe', status: 'open' }] }],
      },
    ],
    ...overrides,
  };
}

test('Golden: Beispielplan aus mollo-planer (rekonstruiert) rendert wie festgelegt', () => {
  const rendered = renderPlaner(JSON.parse(fixture('beispiel-planer.planer.json')));
  assert.equal(rendered, golden('beispiel-planer.md'));
});

test('Golden: gültige Fixture rendert wie festgelegt', () => {
  const rendered = renderPlaner(JSON.parse(fixture('planer-valid.planer.json')));
  assert.equal(rendered, golden('planer-valid.md'));
});

test('Die Fixtures sind in kanonischer Form', () => {
  for (const name of ['planer-valid.planer.json', 'planer-invalid.planer.json', 'beispiel-planer.planer.json']) {
    assert.equal(canonicalText(JSON.parse(fixture(name))), fixture(name), name);
  }
});

test('Kanonische Form hängt nicht von der Schlüsselreihenfolge der Eingabe ab', () => {
  const shuffled = {
    milestones: [{ todos: [], title: 'Start', id: 'm-001' }],
    project: { members: [], locale: 'de', name: 'Demo' },
    revision: 1,
    format: 'planer/2',
  };
  const ordered = { format: 'planer/2', revision: 1, project: { name: 'Demo', locale: 'de', members: [] }, milestones: [{ id: 'm-001', title: 'Start', todos: [] }] };
  assert.equal(canonicalText(shuffled), canonicalText(ordered));
});

test('Milestone ohne Version: Überschrift ohne vM.m, ID-Zeile darunter, kein Status', () => {
  const markdown = renderPlaner(plan());
  assert.match(markdown, /^## Start\n<!-- id: m-001 -->$/m);
  assert.doesNotMatch(markdown, /Status:/);
});

test('Altbestand: Commit „ohne“ wird als „Ohne Commit“ angezeigt', () => {
  const data = plan();
  data.milestones[0].todos[0].tasks[0] = { id: 'k-001', title: 'Alt', status: 'done', commit: 'none' };
  assert.match(renderPlaner(data), /^  - Commit: Ohne Commit$/m);
});

test('Task-Markierungen: [ ], [~], [?], [x]', () => {
  const data = plan();
  data.milestones[0].todos[0].tasks = ['open', 'in_progress', 'review', 'done'].map((status, i) => ({ id: `k-00${i + 1}`, title: `T${i}`, status, commit: status === 'done' ? 'abc1234' : undefined }));
  const markdown = renderPlaner(data);
  assert.match(markdown, /^- \[ \] T0 <!-- id: k-001 -->$/m);
  assert.match(markdown, /^- \[~\] T1 /m);
  assert.match(markdown, /^- \[\?\] T2 /m);
  assert.match(markdown, /^- \[x\] T3 /m);
});

test('Milestone-Beschreibung mit Überschrift oder „- [“ am Zeilenanfang wird abgelehnt', () => {
  const data = plan();
  data.milestones[0].description = 'Text\n\n## Abschnitt';
  assert.throws(() => renderPlaner(data), /als Struktur gelesen würde/);
  data.milestones[0].description = '- [ ] Nebenbei';
  assert.throws(() => renderPlaner(data), /als Struktur gelesen würde/);
});

test('md2json: Story-Zeile, die nicht zum Muster passt, ist ein Fehler', () => {
  const markdown = renderPlaner(JSON.parse(fixture('beispiel-planer.planer.json')))
    .replace('  *Als Entwickler möchte ich eine einzige Reihenfolge-Logik, damit neue Sortierungen nicht an drei Stellen gepflegt werden.*', '  *Als Entwickler, der Reihenfolge will.*');
  assert.throws(() => planerFromMarkdown(markdown), /Story passt nicht zum Muster/);
});

test('Task-Beschreibung mit Überschrift wird eingerückt und bleibt beim Task', () => {
  const data = plan();
  data.milestones[0].todos[0].tasks[0].description = 'Vorher\n\n### Hinweis\n\n- Punkt';
  const markdown = renderPlaner(data);
  assert.match(markdown, /^  ### Hinweis$/m);
  assert.match(markdown, /^  - Punkt$/m);
});

test('CLI json: planer/2 gibt die kanonische Form aus', () => {
  const result = run(['json', fixturePath('planer-valid.planer.json')]);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, fixture('planer-valid.planer.json'));
});

test('CLI json: ungültige planer/2-Datei endet mit Code 1', () => {
  const result = run(['json', fixturePath('planer-invalid.planer.json')]);
  assert.equal(result.status, 1);
  assert.equal(result.stdout, '');
  assert.match(result.stderr, /milestones\[0\]/);
});

test('CLI render: stdout entspricht der Golden-Datei', () => {
  const result = run(['render', fixturePath('planer-valid.planer.json')]);
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stdout, golden('planer-valid.md'));
});

test('CLI render: ein v1-Dokument wird abgelehnt (Code 2)', () => {
  const result = run(['render', fixturePath('valid.md')]);
  assert.equal(result.status, 2);
  assert.match(result.stderr, /planer\/2/);
});

test('changed: die Kopfzeile zeigt „geändert“, md2json liest es zurück', () => {
  const plan = JSON.parse(fixture('planer-valid.planer.json'));
  plan.changed = true;
  const markdown = renderPlaner(plan);

  assert.match(markdown.split('\n')[0], /^<!-- Ansicht von planer\/2, revision \d+, geändert, lang de\. /);
  assert.equal(planerFromMarkdown(markdown).changed, true);
});

test('ohne changed: keine Markierung, md2json setzt kein changed', () => {
  const plan = JSON.parse(fixture('planer-valid.planer.json'));
  const markdown = renderPlaner(plan);

  assert.doesNotMatch(markdown.split('\n')[0], /geändert/);
  assert.equal(planerFromMarkdown(markdown).changed, undefined);
});
