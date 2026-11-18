import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { parseDescription, canonicalDescription, canonicalMarkdown, forbiddenMarkdown } from '../core/richtext.mjs';
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

test('Gemischt und tief verschachtelte Listen sind erlaubt (Aufzählung in nummerierter Liste und umgekehrt)', () => {
  assert.deepEqual(forbiddenMarkdown(read('mixed-list.in.md')), []);
  assert.deepEqual(forbiddenMarkdown(read('nested-deep.in.md')), []);
  const { content } = parseDescription(read('mixed-list.in.md')).doc;
  assert.equal(content[0].type, 'orderedList');
  assert.equal(content[0].content[0].content[1].type, 'bulletList');
  assert.equal(content[1].type, 'bulletList');
  assert.equal(content[1].content[0].content[1].type, 'orderedList');
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

const text = (value, marks = []) => (marks.length === 0 ? { type: 'text', text: value } : { type: 'text', text: value, marks: marks.map((type) => ({ type })) });
const paragraph = (...content) => ({ type: 'paragraph', content });
const doc = (...content) => ({ type: 'doc', content });

test('Kanonische Form: Leerzeichen am Rand von Marks, am Zeilenrand und leere Punkte', () => {
  assert.equal(canonicalMarkdown(doc(paragraph(text('Hallo ', ['bold']), text('Welt'), text(' und ', ['italic']), text('mehr', ['bold', 'italic'])))), '**Hallo** Welt *und* ***mehr***');
  assert.equal(canonicalMarkdown(doc(paragraph(text('  vorn '), { type: 'hardBreak' }, text('  nach  '), { type: 'hardBreak' }))), 'vorn\\\nnach');
  const empty = { type: 'listItem', content: [{ type: 'paragraph' }] };
  const item = (value) => ({ type: 'listItem', content: [paragraph(text(value))] });
  assert.equal(canonicalMarkdown(doc({ type: 'orderedList', content: [item('a'), empty, item('b')] })), '1. a\n2. b');
});

test('Kanonische Form: Text, der Markdown wäre, wird maskiert', () => {
  const lines = ['# kein Titel', '> kein Zitat', '- kein Punkt', '12. keine Liste', '---', '-|-', '&amp; & &', '~~x~~ ~y~', 'a_b*c[d]e<f`g\\h'];
  assert.equal(
    canonicalMarkdown(doc(...lines.map((line) => paragraph(text(line))))),
    ['\\# kein Titel', '\\> kein Zitat', '\\- kein Punkt', '12\\. keine Liste', '\\---', '\\-|-', '\\&amp; & &', '\\~\\~x\\~\\~ ~y~', 'a\\_b\\*c\\[d\\]e\\<f\\`g\\\\h'].join('\n\n'),
  );
  assert.equal(canonicalMarkdown(doc({ type: 'heading', attrs: { level: 2 }, content: [text('C #')] })), '## C \\#');
});

test('Kanonische Form: Absätze in einem Punkt, Listen nach Absatz und fortlaufender Einzug', () => {
  const li = (...content) => ({ type: 'listItem', content });
  const nested = { type: 'orderedList', content: [li(paragraph(text('a')))] };
  assert.equal(canonicalMarkdown(doc({ type: 'bulletList', content: [li(paragraph(text('eins')), paragraph(text('zwei')), nested)] })), '- eins\n\n  zwei\n  1. a');
});

test('Code mit Backticks, Adressen mit Klammern und überlappende Marks', () => {
  assert.equal(canonicalMarkdown(doc(paragraph(text('a``b', ['code']), text(' '), { type: 'text', text: 'x', marks: [{ type: 'link', attrs: { href: 'https://a.ch/x)y' } }] }))), '```a``b``` [x](<https://a.ch/x)y>)');
  assert.equal(canonicalMarkdown(doc(paragraph(text('aa', ['bold']), text('bb', ['bold', 'italic']), text('cc', ['bold'])))), '**aa*bb*cc**');
});
