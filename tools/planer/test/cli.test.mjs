import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const fixture = (name) => fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url));
const run = (...args) => spawnSync(process.execPath, [cli, ...args], { encoding: 'utf8' });

test('validate: gültige Datei endet mit Code 0', () => {
  const result = run('validate', fixture('valid.md'));
  assert.equal(result.status, 0);
  assert.match(result.stdout, /Keine Fehler/);
});

test('validate: ungültige Datei endet mit Code 1 und nennt Zeilen', () => {
  const result = run('validate', fixture('invalid.md'));
  assert.equal(result.status, 1);
  assert.match(result.stdout, /invalid\.md:5 {2}FEHLER/);
});

test('validate: unbekannte Datei endet mit Code 2', () => {
  const result = run('validate', fixture('gibt-es-nicht.md'));
  assert.equal(result.status, 2);
  assert.match(result.stderr, /Datei nicht gefunden/);
});

test('json: gibt das geparste Ergebnis aus', () => {
  const result = run('json', fixture('valid.md'));
  assert.equal(result.status, 0);
  const data = JSON.parse(result.stdout);
  assert.equal(data.milestones.length, 2);
  assert.equal(data.milestones[0].todos[0].tasks[0].commit, 'abc1234');
});

test('json: verweigert ungültige Dateien', () => {
  const result = run('json', fixture('invalid.md'));
  assert.equal(result.status, 1);
  assert.equal(result.stdout, '');
});

test('unbekannter Befehl endet mit Code 1', () => {
  assert.equal(run('nix').status, 1);
});
