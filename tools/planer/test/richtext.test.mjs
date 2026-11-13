import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { parseDescription, canonicalDescription, forbiddenMarkdown } from '../core/richtext.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';

const dir = fileURLToPath(new URL('../../../spec/fixtures/richtext/', import.meta.url));
const read = (name) => readFileSync(dir + name, 'utf8');
const files = readdirSync(dir);

const pairs = files.filter((name) => name.endsWith('.in.md'));
const errorCases = files.filter((name) => name.endsWith('.error.md'));

test('Eingaben aus der Whitelist werden kanonisch geschrieben (Paare .in.md / .out.md)', () => {
  assert.ok(pairs.length >= 5, 'mindestens fünf Paare');
  for (const name of pairs) {
    const expected = read(name.replace(/\.in\.md$/, '.out.md'));
    const { errors } = parseDescription(read(name));
    assert.deepEqual(errors, [], `${name}: keine Fehler erwartet`);
    assert.equal(canonicalDescription(read(name)) + '\n', expected, name);
  }
});

test('Kanonische Form ist stabil: zweites Schreiben ändert nichts', () => {
  for (const name of pairs) {
    const once = canonicalDescription(read(name));
    assert.equal(canonicalDescription(once), once, name);
  }
});

test('Schreibweisen aus §9.5 geben Hinweise, keine Fehler', () => {
  const { errors, hints } = parseDescription(read('underscores.hint.in.md'));
  assert.deepEqual(errors, []);
  assert.ok(hints.some((hint) => hint.includes('Fett mit Unterstrich')));
  assert.ok(hints.some((hint) => hint.includes('Kursiv mit Unterstrich')));
});

test('Konstrukte außerhalb der Whitelist sind Fehler (§9.6)', () => {
  assert.ok(errorCases.length >= 12, 'mindestens zwölf Fehlerfälle');
  for (const name of errorCases) {
    assert.ok(forbiddenMarkdown(read(name)).length > 0, `${name} muss einen Fehler melden`);
  }
});

test('Gemischte Listen sind Fehler, tiefe Verschachtelung ist erlaubt', () => {
  assert.match(forbiddenMarkdown(read('mixed-list.error.md')).join('\n'), /Gemischte verschachtelte Listen/);
  assert.deepEqual(forbiddenMarkdown(read('nested-deep.in.md')), []);
});

test('Validator: Fehler aus der Whitelist führen zur Angabe des Feldes, Schreibweisen nur zum Hinweis', () => {
  const base = JSON.parse(readFileSync(fileURLToPath(new URL('../../../spec/fixtures/planer-valid.planer.json', import.meta.url)), 'utf8'));
  const issues = (description) => {
    const plan = structuredClone(base);
    plan.project.description = description;
    return validatePlaner(JSON.stringify(plan, null, 2) + '\n');
  };

  const bad = issues('Text <b>fett</b>').filter((issue) => issue.severity === 'error');
  assert.equal(bad.length, 1);
  assert.equal(bad[0].path, 'project.description');

  const hint = issues('__fett__ Text');
  assert.equal(hint.filter((issue) => issue.severity === 'error').length, 0);
  assert.ok(hint.some((issue) => issue.severity === 'warning' && issue.path === 'project.description'));
});
