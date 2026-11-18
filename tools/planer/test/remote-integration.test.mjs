/**
 * Integrationstest gegen den echten MCP-Server der App (docs/auftrag-m5.md §2.3, keine Nachbildung).
 * Läuft nur mit MOLLO_PLANER_TEST_URL und MOLLO_PLANER_TEST_TOKEN (mehrere Zugänge, durch Komma getrennt:
 * die App begrenzt auf 60 Anfragen je Minute und Person). Gestartet von scripts/run-remote-tests.mjs
 * (`npm run test:remote` bzw. `composer test:planer-remote`), das eine eigene SQLite-Datenbank mit den Demo-Daten aufsetzt.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { McpClient } from '../cli/planer-remote.mjs';

const url = process.env.MOLLO_PLANER_TEST_URL;
const tokens = (process.env.MOLLO_PLANER_TEST_TOKEN ?? '').split(',').filter(Boolean);
const skip = !url || tokens.length === 0 ? 'MOLLO_PLANER_TEST_URL und MOLLO_PLANER_TEST_TOKEN fehlen (npm run test:remote)' : false;

const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));
const PROJECT = '1';

const dir = mkdtempSync(join(tmpdir(), 'planer-remote-'));
mkdirSync(join(dir, 'docs'));
spawnSync('git', ['init', '-q', dir]);
const file = join(dir, 'docs', 'web.planer.json');

/** Das Werkzeug als Kind-Prozess, wie es ein Mensch ruft. */
function planer(args, token = tokens[0]) {
  const result = spawnSync(process.execPath, [cli, ...args], {
    cwd: dir,
    encoding: 'utf8',
    env: { ...process.env, MOLLO_PLANER_URL: url, MOLLO_PLANER_TOKEN: token, MOLLO_PLANER_PROJECT: PROJECT },
  });
  return { code: result.status, out: result.stdout, err: result.stderr };
}

const read = () => JSON.parse(readFileSync(file, 'utf8'));

test.after(() => rmSync(dir, { recursive: true, force: true }));

test('1. pull legt die Datei an, das zweite pull ist unverändert und fasst sie nicht an', { skip }, () => {
  const first = planer(['pull', 'docs/web.planer.json']);
  assert.equal(first.code, 0, first.err);
  assert.match(first.out, /Angelegt/);
  assert.equal(read().format, 'planer-v2');

  const old = new Date('2020-01-01');
  utimesSync(file, old, old);
  const second = planer(['pull', 'docs/web.planer.json']);
  assert.equal(second.code, 0);
  assert.match(second.out, /unverändert \(Revision \d+\)/);
  assert.equal(statSync(file).mtime.getTime(), old.getTime());
});

test('2. ein Helfer ändert lokal: changed, pull bricht ab (Exit 3), pull --force verwirft', { skip }, () => {
  const added = planer(['add', 'task', '--todo', read().milestones[0].todos[0].id, '--title', 'Nur lokal']);
  assert.equal(added.code, 0, added.err);
  assert.equal(read().changed, true);

  const refused = planer(['pull', 'docs/web.planer.json']);
  assert.equal(refused.code, 3);
  assert.match(refused.err, /Lokale Änderungen/);
  assert.equal(read().changed, true);

  assert.equal(planer(['pull', 'docs/web.planer.json', '--force']).code, 0);
  assert.equal(read().changed, undefined);
  assert.doesNotMatch(readFileSync(file, 'utf8'), /Nur lokal/);
});

test('3. push mit neuem Task: echte ID, Revision +1, kein changed; danach pull unverändert', { skip }, () => {
  const before = read();
  const todo = before.milestones[0].todos[0];
  assert.equal(planer(['add', 'task', '--todo', todo.id, '--title', 'Eingetragen per push'], tokens[1] ?? tokens[0]).code, 0);

  const pushed = planer(['push', 'docs/web.planer.json'], tokens[1] ?? tokens[0]);
  assert.equal(pushed.code, 0, pushed.err);
  assert.match(pushed.out, /1 Änderung: 1 neu/);

  const after = read();
  assert.equal(after.changed, undefined);
  assert.equal(after.revision, before.revision + 1);
  const task = after.milestones[0].todos[0].tasks.find((entry) => entry.title === 'Eingetragen per push');
  assert.match(task.id, /^k-\d+$/);

  const again = planer(['pull', 'docs/web.planer.json'], tokens[1] ?? tokens[0]);
  assert.match(again.out, /unverändert/);
});

test('4. eine Änderung in der App (zweiter Client): push bricht mit dem Revisionshinweis ab', { skip }, async () => {
  const mine = tokens[2] ?? tokens[0];
  planer(['pull', 'docs/web.planer.json', '--force'], mine);
  assert.equal(planer(['status', read().milestones[0].todos[0].tasks[0].id, 'done'], mine).code, 0);
  const fileRevision = read().revision;

  const other = new McpClient({ url, token: tokens[0] });
  const { document } = await other.call('export_plan', { project_id: PROJECT });
  document.milestones[0].title = 'In der App geändert';
  await other.call('import_plan', { project_id: PROJECT, plan: document, mode: 'partial', dry_run: false, notify_assignees: false });

  const refused = planer(['push', 'docs/web.planer.json'], mine);
  assert.equal(refused.code, 4);
  assert.match(refused.err, new RegExp(`Die App ist bei Revision ${fileRevision + 1}, die Datei bei ${fileRevision}\\.`));
  assert.equal(read().changed, true);

  assert.equal(planer(['pull', 'docs/web.planer.json', '--force'], mine).code, 0);
  assert.equal(read().milestones[0].title, 'In der App geändert');
});

test('5. push mit Löschung: ohne --yes Exit 5 mit Titel, mit --yes eingetragen; sync danach unverändert', { skip }, () => {
  const token = tokens[0];
  planer(['pull', 'docs/web.planer.json', '--force'], token);
  const doc = read();
  const gone = doc.milestones[0].todos[0].tasks.at(-1);
  doc.milestones[0].todos[0].tasks = doc.milestones[0].todos[0].tasks.slice(0, -1);
  writeFileSync(file, `${JSON.stringify({ ...doc, changed: true }, null, 2)}\n`);

  const refused = planer(['push', 'docs/web.planer.json'], token);
  assert.equal(refused.code, 5);
  assert.match(refused.err, new RegExp(gone.title));

  const pushed = planer(['push', 'docs/web.planer.json', '--yes'], token);
  assert.equal(pushed.code, 0, pushed.err);
  assert.equal(read().changed, undefined);
  assert.doesNotMatch(readFileSync(file, 'utf8'), new RegExp(gone.title));

  const synced = planer(['sync', 'docs/web.planer.json'], token);
  assert.equal(synced.code, 0);
  assert.match(synced.out, /unverändert/);
});

test('6. ein falscher Zugang endet mit Exit 6 und einem Text, der ihn nicht enthält', { skip }, () => {
  const result = planer(['pull', 'docs/web.planer.json'], 'falscher-zugang-xyz');
  assert.equal(result.code, 6);
  assert.match(result.err, /Zugang ungültig oder abgelaufen/);
  assert.doesNotMatch(result.err + result.out, /falscher-zugang-xyz/);
});
