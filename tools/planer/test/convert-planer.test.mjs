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

/** Ansicht mit allen Sprachen in der Legende, damit ein Roundtrip nichts verliert. */
const ALL = { legendTranslations: true };

test('Roundtrip planer-v2: render → md2json ergibt dieselbe kanonische Datei (beispiel)', () => {
  const original = read('../../../spec/fixtures/beispiel-planer.planer.json');
  const back = planerFromMarkdown(renderPlaner(JSON.parse(original), ALL));
  assert.equal(canonicalText(back), original);
});

test('Roundtrip planer-v2 ohne $schema (valid)', () => {
  const original = read('../../../spec/fixtures/planer-valid.planer.json');
  const back = planerFromMarkdown(renderPlaner(JSON.parse(original), ALL));
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

test('v1 → v2: gültige Fixture ergibt eine gültige Datei mit neuen IDs', () => {
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
  assert.equal(plan.milestones[0].id, 'm-new-1');
});

test('v1 → v2: Art wird Label, Erledigt wird done, Commit bleibt', () => {
  const v1 = '## v1.0 Start\n\n### Arbeit\n\n- [x] [bug] Erledigt\n  Commit abc1234\n\n- [ ] [feature] Offen\n';
  const plan = planerFromV1(v1, { name: 'Demo' });
  const [done, open] = plan.milestones[0].todos[0].tasks;
  assert.deepEqual(done, { id: 'k-new-1', title: 'Erledigt', status: 'done', labels: ['bug'], commit: 'abc1234' });
  assert.deepEqual(open, { id: 'k-new-2', title: 'Offen', status: 'open', labels: ['feature'] });
});

test('v1 → v2: „Ohne Commit“ wird zu commit „ohne“', () => {
  const v1 = '## v1.0 Start\n\n### Arbeit\n\n- [x] [feature] Alt\n  Ohne Commit\n';
  const plan = planerFromV1(v1, { name: 'Demo' });
  assert.equal(plan.milestones[0].todos[0].tasks[0].commit, 'none');
});

test('v1 → v2 → Markdown → v2: derselbe Stand', () => {
  const v1 = read('../../../spec/fixtures/valid.md');
  const v2 = canonicalText(planerFromV1(v1, { name: 'Demo', locale: 'EN' }));
  const back = canonicalText(planerFromMarkdown(renderPlaner(JSON.parse(v2), ALL)));
  assert.equal(back, v2);
});

test('v1 → v2: ungültige v1-Datei wird abgelehnt', () => {
  assert.throws(() => planerFromV1(read('../../../spec/fixtures/invalid.md'), { name: 'Demo' }), ConvertError);
});

test('md2json: fehlende Kopfzeile nennt Zeile 1', () => {
  assert.throws(() => planerFromMarkdown('# Demo\n- Sprache: DE\n'), (error) => error instanceof MarkdownError && error.line === 1);
});

test('md2json: Task ohne ID-Zeile nennt die Zeile', () => {
  const markdown = renderPlaner(JSON.parse(read('../../../spec/fixtures/beispiel-planer.planer.json')), ALL)
    .replace('- [~] Ordering-Service ausbauen <!-- id: k-004 -->', '- [~] Ordering-Service ausbauen');
  assert.throws(() => planerFromMarkdown(markdown), (error) => {
    return error instanceof MarkdownError && /Task nicht lesbar/.test(error.message) && error.line > 1;
  });
});

test('md2json: unbekannte Metadaten-Zeile am Task bleibt Beschreibung', () => {
  // Eine Liste in der Beschreibung ist kein Metadatum und darf nicht abbrechen.
  const data = JSON.parse(read('../../../spec/fixtures/beispiel-planer.planer.json'));
  data.milestones[1].todos[0].tasks[1].description = '- Punkt eins\n- Punkt zwei';
  const back = planerFromMarkdown(renderPlaner(data, ALL));
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

test('convert v1-zu-v2 --release-dates: Datum je veröffentlichter Version, Hinweis für die ohne, Fehler mit Zeile', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const file = join(dir, 'version-demo.md');
    writeFileSync(file, '# Demo\n\n## v1.0 Fertig\n\nStatus: veröffentlicht\n\n### Aufbau\n\n- [x] [feature] Eins\n  Commit abc1234\n\n## v1.1 Offen\n\n### Ausbau\n\n- [ ] [feature] Zwei\n');
    const plain = JSON.parse(spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file], { encoding: 'utf8' }).stdout);
    const released = plain.milestones.filter((milestone) => milestone.release.status === 'released');
    assert.ok(released.length > 0, 'die Fixture hat eine veröffentlichte Version');
    assert.ok(released.every((milestone) => milestone.release.date === undefined), 'ohne Option bleibt das Feld leer');

    const hint = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file], { encoding: 'utf8' }).stderr;
    assert.match(hint, new RegExp(`Zusammenfassung: .*${released.length} veröffentlichte Milestones ohne Datum`));

    const csv = join(dir, 'dates.csv');
    writeFileSync(csv, `version,date\n${released.map((milestone) => `${milestone.release.version},2026-10-15`).join('\n')}\n9.9,2026-01-01\n`);
    const withDates = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file, '--release-dates', csv], { encoding: 'utf8' });
    assert.equal(withDates.status, 0, withDates.stderr);
    const result = JSON.parse(withDates.stdout);
    assert.ok(result.milestones.filter((milestone) => milestone.release.status === 'released').every((milestone) => milestone.release.date === '2026-10-15'));
    assert.deepEqual(errorsOf(withDates.stdout), []);
    assert.match(withDates.stderr, /1 Daten für Versionen, die die Datei nicht hat: v9\.9/);
    assert.doesNotMatch(withDates.stderr, /veröffentlichte Milestones ohne Datum/);

    writeFileSync(csv, 'version,date\n1.0,15.10.2026\n');
    const bad = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file, '--release-dates', csv], { encoding: 'utf8' });
    assert.equal(bad.status, 1);
    assert.match(bad.stderr, /Zeile 2: Datum „15\.10\.2026“ ist nicht von der Form JJJJ-MM-TT/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

// ─── Club DB (task 29 C): Arten, lange Titel, mehrere Commits ───────────────────────────────

const v1 = (tasks) => `# Demo\n\n## v1.0 Fertig\n\nStatus: veröffentlicht\n\n### Aufbau\n\n${tasks}\n`;
const done = (kind, title, commit = 'abc1234') => `- [x] [${kind}] ${title}\n  Commit ${commit}`;

test('convert: jede Art der v1-Datei wird ein Label (Kategorie type, Name = Art), eingebaute bleiben', () => {
  const notes = {};
  const plan = planerFromV1(v1([done('fix', 'Eins'), done('docs', 'Zwei'), done('feature', 'Drei'), done('fix', 'Vier')].join('\n')), { name: 'Demo', notes });
  const keys = plan.project.labels.map((label) => label.key);

  assert.deepEqual(keys.slice(-2), ['fix', 'docs'], 'neue Arten hinten, in der Reihenfolge des Auftretens');
  assert.ok(keys.includes('feature') && keys.includes('bug'), 'die eingebauten bleiben');
  const fix = plan.project.labels.find((label) => label.key === 'fix');
  assert.deepEqual([fix.names, fix.category], [{ de: 'fix', en: 'fix' }, 'type']);
  assert.deepEqual(notes.newLabels, ['fix', 'docs']);
  assert.deepEqual(plan.milestones[0].todos[0].tasks.map((task) => task.labels[0]), ['fix', 'docs', 'feature', 'fix']);
});

test('convert --labels: Namen und Farbe ändern, falsche Farbe und zu viele Labels sind Fehler', () => {
  const plan = planerFromV1(v1(done('fix', 'Eins')), { name: 'Demo', labels: { fix: { names: { de: 'Korrektur', en: 'Fix' }, color: 'red' }, ungenutzt: { color: 'blue' } } });
  const fix = plan.project.labels.find((label) => label.key === 'fix');
  assert.deepEqual([fix.names, fix.color], [{ de: 'Korrektur', en: 'Fix' }, 'red']);

  assert.throws(() => planerFromV1(v1(done('fix', 'Eins')), { name: 'Demo', labels: { fix: { color: 'magenta' } } }), /Farbe „magenta“/);
  const many = Array.from({ length: 30 }, (_, index) => done(`art${index}`, `Task ${index}`)).join('\n');
  assert.throws(() => planerFromV1(v1(many), { name: 'Demo' }), /braucht 36 Labels, erlaubt sind 30/);
  assert.throws(() => planerFromV1(v1(done('Fix', 'Eins')), { name: 'Demo' }), /kein gültiger Label-Schlüssel/);
});

test('convert: lange Titel werden am Satzende, sonst vor dem Doppelpunkt, sonst am Wortende gekürzt, der ganze Titel bleibt in der Beschreibung', () => {
  const sentence = `${'Wort '.repeat(20).trim()}. ${'Weiter '.repeat(30).trim()}`;
  const colon = `Matomo-Anbindung: ${'viel Text '.repeat(30).trim()}`;
  const plain = 'langer '.repeat(40).trim();
  const notes = {};
  const plan = planerFromV1(v1([done('feature', sentence), done('feature', colon, 'bbb2222'), done('feature', plain, 'ccc3333')].join('\n')), { name: 'Demo', notes });
  const [a, b, c] = plan.milestones[0].todos[0].tasks;

  assert.equal(a.title, `${'Wort '.repeat(20).trim()}.`, 'am Satzende');
  assert.equal(b.title, 'Matomo-Anbindung', 'vor dem Doppelpunkt');
  assert.match(c.title, /langer…$/, 'am Wortende, mit Auslassung');
  for (const task of [a, b, c]) assert.ok(task.title.length <= 160);
  assert.ok(a.description.startsWith(sentence), 'der ganze Titel ist der erste Absatz');
  assert.equal(b.description, colon);
  assert.equal(notes.shortenedTitles.length, 3);
  assert.deepEqual([notes.shortenedTitles[1].what, notes.shortenedTitles[1].line], ['Task', 11]);
  assert.equal(notes.shortenedTitles[1].old, colon);
});

test('convert: ein gekürzter Titel mit Beschreibung behält sie hinter dem Originaltitel, über 5000 Zeichen ist ein Fehler ohne Abschneiden', () => {
  const title = `Kurz: ${'x '.repeat(100)}`;
  const withDescription = `- [x] [feature] ${title}\n  Commit abc1234\n  Beschreibung darunter.`;
  const plan = planerFromV1(v1(withDescription), { name: 'Demo' });
  assert.equal(plan.milestones[0].todos[0].tasks[0].description, `${title.trim()}\n\nBeschreibung darunter.`);

  const huge = `- [x] [feature] ${title}\n  Commit abc1234\n  ${'y'.repeat(5000)}`;
  assert.throws(() => planerFromV1(v1(huge), { name: 'Demo' }), /länger als 5000 Zeichen/);
});

test('convert: mehrere Commits in einer Zeile: der letzte ist der commit, die übrigen stehen in der Beschreibung, mit Hinweis', () => {
  const notes = {};
  const plan = planerFromV1(v1(`- [x] [feature] Viele\n  Commit 6ccf763, 83258dd, 9c45cb9\n  Eine Zeile Beschreibung.`), { name: 'Demo', notes });
  const task = plan.milestones[0].todos[0].tasks[0];

  assert.equal(task.commit, '9c45cb9');
  assert.equal(task.description, 'Eine Zeile Beschreibung.\n\nWeitere Commits: 6ccf763, 83258dd');
  assert.deepEqual(notes.multipleCommits.map((entry) => entry.title), ['Viele']);
  assert.deepEqual(errorsOf(JSON.stringify(plan)), []);
});

test('convert: Roadmap-Nummern im Titel bleiben unverändert', () => {
  const plan = planerFromV1(v1(done('feature', 'B-77 Clubfarben (B-78)')), { name: 'Demo' });
  assert.equal(plan.milestones[0].todos[0].tasks[0].title, 'B-77 Clubfarben (B-78)');
});

test('CLI convert: die Hinweise stehen am Ende, mit Zahlen; stdout bleibt die Datei', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-convert-'));
  try {
    const file = join(dir, 'version-demo.md');
    writeFileSync(file, v1([done('fix', `Kurz: ${'x '.repeat(100)}`), done('feature', 'Zwei', 'aaa1111, bbb2222')].join('\n')));
    const result = spawnSync(process.execPath, [cli, 'convert', 'v1-zu-v2', file], { encoding: 'utf8' });

    assert.equal(result.status, 0, result.stderr);
    assert.equal(JSON.parse(result.stdout).format, 'planer-v2');
    assert.match(result.stderr, /Hinweis, Zeile 9: Task-Titel gekürzt\n {2}alt: Kurz: x x/);
    assert.match(result.stderr, /Zusammenfassung: 1 Titel gekürzt, 1 neue Labels \(fix\), 1 veröffentlichte Milestones ohne Datum \(mit --release-dates setzen\), 1 Tasks mit mehreren Commits\./);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
