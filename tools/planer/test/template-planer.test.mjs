import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { templateIssues, toTemplate } from '../core/template.mjs';
import { validatePlaner } from '../core/validate-planer.mjs';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const templatesDir = fileURLToPath(new URL('../../../spec/templates/', import.meta.url));
const fixturePath = fileURLToPath(new URL('../../../spec/fixtures/beispiel-planer.planer.json', import.meta.url));
const read = (path) => readFileSync(path, 'utf8');
const run = (args) => spawnSync(process.execPath, [cli, ...args], { encoding: 'utf8' });
const errorsOf = (plan) => validatePlaner(JSON.stringify(plan, null, 2)).filter((issue) => issue.severity === 'error');

test('die mitgelieferten Vorlagen bestehen validate --template', () => {
  const files = readdirSync(templatesDir).filter((name) => name.endsWith('.planer.json'));
  assert.equal(files.length, 3);
  for (const file of files) {
    const result = run(['validate', join(templatesDir, file), '--template']);
    assert.equal(result.status, 0, `${file}: ${result.stdout}`);
    assert.deepEqual(templateIssues(JSON.parse(read(join(templatesDir, file)))), []);
  }
});

test('template macht aus einer Planer-Datei eine Vorlage, die ihre Regeln erfüllt', () => {
  const template = toTemplate(JSON.parse(read(fixturePath)));
  assert.deepEqual(templateIssues(template), []);
  assert.deepEqual(errorsOf(template), []);
  assert.equal(template.revision, 0);
  assert.deepEqual(template.project.members, []);
  assert.equal(template.milestones[0].id, 'm-new-1');
  assert.equal(template.milestones[0].todos[0].id, 't-new-1');
  assert.equal(template.milestones[0].todos[0].tasks[0].status, 'planned');
  assert.equal(template.milestones[0].todos[0].tasks[0].assigneeEmail, undefined);
  assert.equal(template.milestones[0].todos[0].tasks[0].dueDate, undefined);
});

test('template-Befehl schreibt die Vorlage, die validate --template besteht', () => {
  const dir = mkdtempSync(join(tmpdir(), 'mollo-template-'));
  try {
    const out = join(dir, 'vorlage.planer.json');
    const made = run(['template', fixturePath, '--out', out]);
    assert.equal(made.status, 0, made.stderr);
    assert.equal(run(['validate', out, '--template']).status, 0);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('Verletzungen der Vorlagen-Regeln werden benannt', () => {
  const base = toTemplate(JSON.parse(read(fixturePath)));
  const cases = [
    ['revision', (d) => { d.revision = 3; }, 'revision'],
    ['changed', (d) => { d.changed = true; }, 'changed'],
    ['Mitglied', (d) => { d.project.members = [{ email: 'a@example.org', role: 'member' }]; }, 'project.members'],
    ['DB-ID', (d) => { d.milestones[0].id = 'm-12'; }, 'milestones[0].id'],
    ['Task-Status', (d) => { d.milestones[0].todos[0].tasks[0].status = 'done'; }, 'milestones[0].todos[0].tasks[0].status'],
    ['Fälligkeit', (d) => { d.milestones[0].dueDate = '2027-01-01'; }, 'milestones[0].dueDate'],
    ['Zuweisung', (d) => { d.milestones[0].todos[0].tasks[0].assigneeEmail = 'oliver@mollo.ch'; }, 'milestones[0].todos[0].tasks[0].assigneeEmail'],
    ['Commit', (d) => { d.milestones[0].todos[0].tasks[0].commit = 'abc1234'; }, 'milestones[0].todos[0].tasks[0].commit'],
    ['Release', (d) => { d.milestones[0].release = { version: '1.0', status: 'released' }; }, 'milestones[0].release.status'],
    ['Labels', (d) => { delete d.project.labels; }, 'project.labels'],
  ];
  for (const [name, mutate, path] of cases) {
    const doc = structuredClone(base);
    mutate(doc);
    const paths = templateIssues(doc).map((issue) => issue.path);
    assert.ok(paths.includes(path), `${name}: erwartet ${path}, gefunden ${paths.join(', ')}`);
  }
});
