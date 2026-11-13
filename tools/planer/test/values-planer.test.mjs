import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { renderPlaner } from '../core/render-planer.mjs';
import { planerFromMarkdown } from '../core/planer-from-md.mjs';
import { canonicalText } from '../core/planer-canonical.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';
import { planerFromV1 } from '../core/v1-to-planer.mjs';

const fixture = (name) => JSON.parse(readFileSync(fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url)), 'utf8'));
const errors = (plan) => validatePlaner(JSON.stringify(plan, null, 2)).filter((issue) => issue.severity === 'error');
const ALL = { legendTranslations: true };

test('Werte: Sprache de/en, Freigabe planned/released, Commit none', () => {
  const plan = fixture('beispiel-planer.planer.json');
  assert.deepEqual(errors(plan), []);

  const upper = structuredClone(plan);
  upper.project.locale = 'DE';
  assert.ok(errors(upper).some((issue) => issue.path === 'project.locale'));

  const german = structuredClone(plan);
  german.milestones[0].release.status = 'veröffentlicht';
  assert.ok(errors(german).some((issue) => issue.path === 'milestones[0].release.status'));
});

test('Status idea und planned: Markierungen [i] und [p], Roundtrip', () => {
  const plan = fixture('beispiel-planer.planer.json');
  const task = plan.milestones[0].todos[0].tasks[0];
  task.status = 'idea';
  const second = plan.milestones[0].todos[0].tasks[1];
  second.status = 'planned';
  second.commit = undefined;
  const markdown = renderPlaner(plan);
  assert.match(markdown, /^- \[i\] /m);
  assert.match(markdown, /^- \[p\] /m);
  const back = planerFromMarkdown(markdown);
  assert.equal(back.milestones[0].todos[0].tasks[0].status, 'idea');
  assert.equal(back.milestones[0].todos[0].tasks[1].status, 'planned');
});

test('Englische Ansicht: feste Wörter übersetzt, Roundtrip mit allen Sprachen bleibt gleich', () => {
  const plan = fixture('beispiel-planer.planer.json');
  const markdown = renderPlaner(plan, { lang: 'en', legendTranslations: true });
  assert.match(markdown.split('\n')[0], /lang en\./);
  assert.match(markdown, /^- Language: de$/m);
  assert.match(markdown, /^## Labels$/m);
  assert.match(markdown, /^\| Key \| Name \| de \| Category \| Colour \|$/m);
  assert.match(markdown, /^  - Due: /m);
  assert.equal(canonicalText(planerFromMarkdown(markdown)), canonicalText(plan));
});

test('Legende ohne Übersetzungen: die Projektsprache bleibt als Spalte erhalten, der Roundtrip verliert nichts', () => {
  const plan = fixture('beispiel-planer.planer.json');
  const markdown = renderPlaner(plan, { lang: 'en' });
  assert.match(markdown, /^\| Key \| Name \| de \| Category \| Colour \|$/m);
  assert.doesNotMatch(markdown, /^\| Key \| Name \| de \| en/m);
  assert.equal(canonicalText(planerFromMarkdown(markdown)), canonicalText(plan));
});

test('Labels: der Name in der Sprache des Projekts ist Pflicht', () => {
  const plan = fixture('planer-valid.planer.json');
  plan.project.labels[0].names = { en: 'Backend' };
  assert.ok(errors(plan).some((issue) => issue.path === 'project.labels[0].names'));
});

test('Labels: doppelte Namen je Sprache werden abgelehnt', () => {
  const plan = fixture('beispiel-planer.planer.json');
  plan.project.labels[1].names = { de: 'Feature', en: 'Bug' };
  assert.ok(errors(plan).some((issue) => issue.path === 'project.labels[1].names.de'));
});

test('convert v1 schreibt die Labels der Vorlage in beiden Sprachen und Werte in der Datei', () => {
  const v1 = '## v1.0 Start\n\n### Arbeit\n\n- [x] [feature] Alt\n  Ohne Commit\n';
  const plan = planerFromV1(v1, { name: 'Demo', locale: 'EN' });
  assert.equal(plan.project.locale, 'en');
  assert.deepEqual(plan.project.labels.find((label) => label.key === 'feature').names, { de: 'Feature', en: 'feature' });
  assert.equal(plan.milestones[0].release.status, 'planned');
  assert.equal(plan.milestones[0].todos[0].tasks[0].commit, 'none');
  assert.deepEqual(errors(plan), []);
});

test('Legende: Übersetzungen fehlen, ein Name ohne Legende ist ein Fehler mit Zeile', () => {
  const plan = fixture('beispiel-planer.planer.json');
  const markdown = renderPlaner(plan, ALL).replace('| backend | Backend | Backend | area | cyan |', '| backend | Backend | – | area | cyan |');
  const back = planerFromMarkdown(markdown);
  assert.equal(back.project.labels.find((label) => label.key === 'backend').names.en, undefined);
  assert.throws(() => planerFromMarkdown(markdown.replace('- Labels: Backend, Feature', '- Labels: Unbekannt')), /steht nicht in der Legende/);
});

test('CLI render: --lang en und --legend-translations, unbekannte Sprache endet mit Code 2', async () => {
  const { spawnSync } = await import('node:child_process');
  const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
  const source = fileURLToPath(new URL('../../../spec/fixtures/beispiel-planer.planer.json', import.meta.url));
  const english = spawnSync(process.execPath, [cli, 'render', source, '--lang', 'en', '--legend-translations'], { encoding: 'utf8' });
  assert.equal(english.status, 0, english.stderr);
  assert.match(english.stdout, /lang en\./);
  assert.match(english.stdout, /^\| Key \| Name \| de \| Category \| Colour \|$/m);
  assert.match(english.stdout, /^\| feature \| Feature \| Feature \| type \| green \|$/m);
  const wrong = spawnSync(process.execPath, [cli, 'render', source, '--lang', 'fr'], { encoding: 'utf8' });
  assert.equal(wrong.status, 2);
});
