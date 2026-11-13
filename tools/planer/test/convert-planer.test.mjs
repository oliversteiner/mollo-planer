import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync, writeFileSync, copyFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { planerFromV1, ConvertError } from '../core/v1-to-planer.mjs';
import { planerFromMarkdown, MarkdownError } from '../core/planer-from-md.mjs';
import { renderPlaner } from '../core/render-planer.mjs';
import { canonicalText } from '../core/planer-canonical.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const read = (path) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), 'utf8');
const fixturePath = (name) => fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url));
const errorsOf = (text) => validatePlaner(text).filter((issue) => issue.severity === 'error');

/** $schema ist kein Inhalt. Die Ansicht trägt es nicht, deshalb vergleicht der Roundtrip ohne dieses Feld. */
function withoutSchema(text) {
  const data = JSON.parse(text);
  delete data.$schema;
  return canonicalText(data);
}

test('Roundtrip planer/2: render → md2json ergibt dieselbe kanonische Datei (beispiel)', () => {
  const original = read('../../../spec/fixtures/beispiel-planer.planer.json');
  const back = planerFromMarkdown(renderPlaner(JSON.parse(original)));
  assert.equal(canonicalText(back), original);
});

test('Roundtrip planer/2 ohne $schema (valid)', () => {
  const original = read('../../../spec/fixtures/planer-valid.planer.json');
  const back = planerFromMarkdown(renderPlaner(JSON.parse(original)));
  assert.equal(canonicalText(back), withoutSchema(original));
});

test('Golden: md2json liest die Golden-Datei des Beispiels zurück', () => {
  const back = planerFromMarkdown(read('../../../spec/fixtures/golden/beispiel-planer.md'));
  // Die Golden-Datei weicht bei der Story von k-001 ab (Fixture-Wortlaut), deshalb nur Struktur und Zahlen prüfen.
  assert.equal(back.milestones.length, 2);
  assert.equal(back.milestones[0].todos[0].tasks[0].userStory.role, 'Owner');
  assert.equal(back.project.members[0].role, 'owner');
  assert.equal(back.revision, 3);
});

test('v1 → v2: gültige Fixture ergibt eine gültige Datei mit fortlaufenden IDs', () => {
  const plan = planerFromV1(read('../../../spec/fixtures/valid.md'), { name: 'Demo', locale: 'DE' });
  const text = canonicalText(plan);
  assert.deepEqual(errorsOf(text), []);

  const ids = [];
  for (const m of plan.milestones) {
    ids.push(m.id);
    for (const t of m.todos) {
      ids.push(t.id);
      for (const k of t.tasks) ids.push(k.id);
    }
  }
  assert.equal(new Set(ids).size, ids.length, 'IDs sind eindeutig');
  assert.equal(plan.milestones[0].id, 'm-001');
});

test('v1 → v2: Art wird Label, Erledigt wird done, Commit bleibt', () => {
  const v1 = '## v1.0 Start\n\n### Arbeit\n\n- [x] [bug] Erledigt\n  Commit abc1234\n\n- [ ] [feature] Offen\n';
  const plan = planerFromV1(v1, { name: 'Demo' });
  const [done, open] = plan.milestones[0].todos[0].tasks;
  assert.deepEqual(done, { id: 'k-001', title: 'Erledigt', status: 'done', labelNames: ['bug'], commit: 'abc1234' });
  assert.deepEqual(open, { id: 'k-002', title: 'Offen', status: 'open', labelNames: ['feature'] });
});

test('v1 → v2: „Ohne Commit“ wird zu commit „ohne“', () => {
  const v1 = '## v1.0 Start\n\n### Arbeit\n\n- [x] [feature] Alt\n  Ohne Commit\n';
  const plan = planerFromV1(v1, { name: 'Demo' });
  assert.equal(plan.milestones[0].todos[0].tasks[0].commit, 'ohne');
});

test('v1 → v2 → Markdown → v2: derselbe Stand', () => {
  const v1 = read('../../../spec/fixtures/valid.md');
  const v2 = canonicalText(planerFromV1(v1, { name: 'Demo', locale: 'EN' }));
  const back = canonicalText(planerFromMarkdown(renderPlaner(JSON.parse(v2))));
  assert.equal(back, v2);
});

test('v1 → v2: ungültige v1-Datei wird abgelehnt', () => {
  assert.throws(() => planerFromV1(read('../../../spec/fixtures/invalid.md'), { name: 'Demo' }), ConvertError);
});

test('md2json: fehlende Kopfzeile nennt Zeile 1', () => {
  assert.throws(() => planerFromMarkdown('# Demo\n- Sprache: DE\n'), (error) => error instanceof MarkdownError && error.line === 1);
});

test('md2json: Task ohne ID-Zeile nennt die Zeile', () => {
  const markdown = renderPlaner(JSON.parse(read('../../../spec/fixtures/beispiel-planer.planer.json')))
    .replace('- [~] Ordering-Service ausbauen <!-- id: k-004 -->', '- [~] Ordering-Service ausbauen');
  assert.throws(() => planerFromMarkdown(markdown), (error) => {
    return error instanceof MarkdownError && /Task nicht lesbar/.test(error.message) && error.line > 1;
  });
});

test('md2json: unbekannte Metadaten-Zeile am Task bleibt Beschreibung', () => {
  // Eine Liste in der Beschreibung ist kein Metadatum und darf nicht abbrechen.
  const data = JSON.parse(read('../../../spec/fixtures/beispiel-planer.planer.json'));
  data.milestones[1].todos[0].tasks[1].description = '- Punkt eins\n- Punkt zwei';
  const back = planerFromMarkdown(renderPlaner(data));
  assert.equal(back.milestones[1].todos[0].tasks[1].description, '- Punkt eins\n- Punkt zwei');
});

test('CLI convert v1-zu-v2: Ausgabe ist gültig, Name aus dem Dateinamen', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const file = join(dir, 'version-demo.md');
    copyFileSync(fixturePath('valid.md'), file);
    const result = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
    assert.deepEqual(errorsOf(result.stdout), []);
    assert.equal(JSON.parse(result.stdout).project.name, 'demo');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('CLI convert v1-zu-v2: ohne ableitbaren Namen Code 2, mit --name läuft es', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const file = join(dir, 'liste.md');
    copyFileSync(fixturePath('valid.md'), file);
    assert.equal(spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file], { encoding: 'utf8' }).status, 2);
    const named = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file, '--name', 'Liste'], { encoding: 'utf8' });
    assert.equal(named.status, 0, named.stderr);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('CLI convert md2json: Golden-Datei wird exakt zur kanonischen Datei', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const out = join(dir, 'beispiel.planer.json');
    const result = spawnSync(process.execPath, [cli, 'convert', 'md2json', fixturePath('golden/beispiel-planer.md'), '--out', out], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
    // Die Golden-Datei enthält den Wortlaut der Story aus der Fixture. Deshalb wird nur die Form verglichen.
    const back = JSON.parse(readFileSync(out, 'utf8'));
    assert.deepEqual(errorsOf(readFileSync(out, 'utf8')), []);
    assert.equal(back.milestones[0].title, 'Ordering-Grundlage');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('CLI convert: unbekannter Modus endet mit Code 2', () => {
  const result = spawnSync(process.execPath, [cli, 'convert', 'nix', fixturePath('valid.md')], { encoding: 'utf8' });
  assert.equal(result.status, 2);
});

test('CLI convert md2json: ungültige Ansicht endet mit Code 1 und nennt die Zeile', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const file = join(dir, 'kaputt.md');
    writeFileSync(file, '# Demo\n');
    const result = spawnSync(process.execPath, [cli, 'convert', 'md2json', file], { encoding: 'utf8' });
    assert.equal(result.status, 1);
    assert.match(result.stderr, /Zeile 1/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
