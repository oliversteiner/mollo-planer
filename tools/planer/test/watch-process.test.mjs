import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

/**
 * `pull --watch` als echtes Programm (docs/fix-m5.md F8.4). Die Einheitstests mit Zeit-Attrappe sehen nicht,
 * ob Node das Programm mitten im Warten beendet; hier läuft bin/mollo-planer.mjs als Kindprozess gegen einen
 * kleinen lokalen Server, der wie die App antwortet und bei jeder Anfrage eine höhere Revision liefert.
 */
const cli = fileURLToPath(new URL('../cli/bin/mollo-planer.mjs', import.meta.url));

function plan(revision) {
  return {
    format: 'planer-v2',
    revision,
    project: { name: 'Demo', locale: 'de', members: [] },
    milestones: [{ id: 'm-1', title: 'Start', todos: [] }],
  };
}

test('pull --watch läuft über mehrere Intervalle weiter, holt Änderungen und endet mit Strg-C (SIGINT) mit Exit 0', async () => {
  let revision = 0;
  const server = createServer((request, response) => {
    let body = '';
    request.on('data', (chunk) => { body += chunk; });
    request.on('end', () => {
      const call = JSON.parse(body);
      const result = call.method === 'initialize'
        ? {}
        : { content: [{ type: 'text', text: JSON.stringify({ document: plan(++revision) }) }], isError: false };
      response.setHeader('Content-Type', 'application/json');
      response.end(JSON.stringify({ jsonrpc: '2.0', id: call.id, result }));
    });
  });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));

  const dir = mkdtempSync(join(tmpdir(), 'planer-watch-'));
  mkdirSync(join(dir, 'docs'));
  spawnSync('git', ['init', '-q', dir]);
  const file = join(dir, 'docs', 'x.planer.json');

  const child = spawn(process.execPath, [cli, 'pull', 'docs/x.planer.json', '--watch', '--interval', '0.3'], {
    cwd: dir,
    env: {
      ...process.env,
      MOLLO_PLANER_URL: `http://127.0.0.1:${server.address().port}`,
      MOLLO_PLANER_TOKEN: 'test-token',
      MOLLO_PLANER_PROJECT: '1',
      MOLLO_PLANER_WATCH_MIN: '0.2',
    },
  });
  let stderr = '';
  child.stderr.on('data', (chunk) => { stderr += chunk; });
  const exited = new Promise((resolve) => child.on('exit', (code, signal) => resolve({ code, signal })));

  try {
    // Nach gut zwei Intervallen läuft es noch und hat die Datei mehrmals aktualisiert.
    await new Promise((resolve) => setTimeout(resolve, 1500));
    assert.equal(child.exitCode, null, `beendet zu früh: ${stderr}`);
    assert.ok(JSON.parse(readFileSync(file, 'utf8')).revision >= 3, 'mindestens drei Durchläufe');

    child.kill('SIGINT');
    const { code } = await Promise.race([exited, new Promise((resolve) => setTimeout(() => resolve({ code: 'Zeitüberschreitung' }), 3000))]);
    assert.equal(code, 0, stderr);
    assert.doesNotMatch(stderr, /unsettled top-level await/);
  } finally {
    child.kill('SIGKILL');
    server.close();
    rmSync(dir, { recursive: true, force: true });
  }
});
