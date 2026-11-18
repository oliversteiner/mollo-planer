import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chmodSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { canonicalText } from '../core/planer-canonical.mjs';
import { McpClient, findConfig, resolveSettings } from '../cli/planer-remote.mjs';
import { EXIT, runSync } from '../cli/planer-sync.mjs';

/** Ein Plan der „App“ in der Revision n. */
function plan(revision, extra = {}) {
  return {
    format: 'planer-v2',
    revision,
    project: { name: 'Demo', locale: 'de', members: [] },
    milestones: [{ id: 'm-1', title: 'Start', todos: [{ id: 't-1', title: 'Arbeit', tasks: [{ id: 'k-1', title: 'Aufgabe', status: 'open' }] }] }],
    ...extra,
  };
}

const SECRET = 'SECRET-TOKEN-1234';

/**
 * Eine gefälschte App: `handlers` beantworten Werkzeuge, `status` erzwingt einen HTTP-Status.
 * Gibt fetch zurück und merkt sich die Aufrufe.
 */
function fakeApp({ handlers = {}, status = 200, failWith = null } = {}) {
  const calls = [];
  const fetch = async (url, options) => {
    if (failWith) {
      throw failWith;
    }
    const body = JSON.parse(options.body);
    calls.push({ url, headers: options.headers, method: body.method, name: body.params?.name, args: body.params?.arguments });
    if (status !== 200) {
      return new Response('{}', { status });
    }
    if (body.method === 'initialize') {
      return Response.json({ jsonrpc: '2.0', id: body.id, result: { protocolVersion: '2025-06-18' } });
    }
    const handler = handlers[body.params.name];
    const outcome = handler(body.params.arguments, calls);
    return Response.json({
      jsonrpc: '2.0',
      id: body.id,
      result: { content: [{ type: 'text', text: JSON.stringify(outcome.data) }], isError: outcome.isError === true },
    });
  };
  return { fetch, calls };
}

function workspace() {
  const dir = mkdtempSync(join(tmpdir(), 'planer-sync-'));
  mkdirSync(join(dir, 'docs'));
  spawnSync('git', ['init', '-q', dir]);
  const out = [];
  const err = [];
  const ctx = (fetch, env = {}) => ({
    cwd: dir,
    env: { MOLLO_PLANER_URL: 'https://planer.example', MOLLO_PLANER_TOKEN: SECRET, MOLLO_PLANER_PROJECT: '7', ...env },
    fetch,
    out: (line) => out.push(line),
    err: (line) => err.push(line),
    stopped: () => false,
    sleep: async () => {},
  });
  return { dir, out, err, ctx, file: join(dir, 'docs', 'x.planer.json'), cleanup: () => rmSync(dir, { recursive: true, force: true }) };
}

const exportApp = (revision, doc = plan(revision)) => fakeApp({ handlers: { export_plan: () => ({ data: { document: doc } }) } });

// ─── Konfiguration ───────────────────────────────────────────────────

test('Konfiguration: Umgebung schlägt Datei, Option schlägt Umgebung, Zuordnung nach Pfad', () => {
  const w = workspace();
  try {
    writeFileSync(join(w.dir, '.mollo-planer'), JSON.stringify({ url: 'https://aus-datei', token: 'aus-datei', projects: { 'docs/x.planer.json': 5 } }));
    writeFileSync(join(w.dir, '.gitignore'), '.mollo-planer\n');
    const warn = () => {};

    const fromFile = resolveSettings({ env: {}, cwd: w.dir, filePath: 'docs/x.planer.json', warn });
    assert.deepEqual([fromFile.url, fromFile.token, fromFile.project], ['https://aus-datei', 'aus-datei', '5']);

    const env = { MOLLO_PLANER_URL: 'https://aus-env/', MOLLO_PLANER_TOKEN: 'aus-env', MOLLO_PLANER_PROJECT: '6' };
    const fromEnv = resolveSettings({ env, cwd: w.dir, filePath: 'docs/x.planer.json', warn });
    assert.deepEqual([fromEnv.url, fromEnv.token, fromEnv.project], ['https://aus-env', 'aus-env', '6']);

    const option = resolveSettings({ env, cwd: w.dir, filePath: 'docs/x.planer.json', projectOption: '9', warn });
    assert.equal(option.project, '9');
  } finally {
    w.cleanup();
  }
});

test('Konfiguration: sucht aufwärts bis zur Git-Wurzel und nicht darüber hinaus', () => {
  const w = workspace();
  try {
    mkdirSync(join(w.dir, 'a', 'b'), { recursive: true });
    writeFileSync(join(w.dir, '.mollo-planer'), JSON.stringify({ url: 'https://x', token: 't' }));
    assert.equal(findConfig(join(w.dir, 'a', 'b')).path, join(w.dir, '.mollo-planer'));

    const outer = mkdtempSync(join(tmpdir(), 'planer-outer-'));
    mkdirSync(join(outer, 'repo', '.git'), { recursive: true });
    writeFileSync(join(outer, '.mollo-planer'), JSON.stringify({ url: 'https://draussen', token: 't' }));
    assert.equal(findConfig(join(outer, 'repo')), null);
    rmSync(outer, { recursive: true, force: true });
  } finally {
    w.cleanup();
  }
});

test('Konfiguration: warnt, wenn der Zugang in einer nicht ignorierten Datei steht, nicht wenn sie ignoriert ist', () => {
  const w = workspace();
  try {
    writeFileSync(join(w.dir, '.mollo-planer'), JSON.stringify({ url: 'https://x', token: 'geheim' }), { mode: 0o600 });
    const warnings = [];
    resolveSettings({ env: {}, cwd: w.dir, filePath: 'docs/x.planer.json', warn: (line) => warnings.push(line) });
    assert.equal(warnings.length, 1);
    assert.doesNotMatch(warnings[0], /geheim/);

    writeFileSync(join(w.dir, '.gitignore'), '.mollo-planer\n');
    const none = [];
    resolveSettings({ env: {}, cwd: w.dir, filePath: 'docs/x.planer.json', warn: (line) => none.push(line) });
    assert.deepEqual(none, []);
  } finally {
    w.cleanup();
  }
});

test('Konfiguration: warnt, wenn .mollo-planer mit Zugang für andere lesbar ist, nicht bei 600 und nicht ohne Zugang (F8.5)', { skip: process.platform === 'win32' }, () => {
  const w = workspace();
  try {
    writeFileSync(join(w.dir, '.gitignore'), '.mollo-planer\n');
    const config = join(w.dir, '.mollo-planer');
    const warningsFor = (data, mode, env = {}) => {
      rmSync(config, { force: true });
      writeFileSync(config, JSON.stringify(data));
      chmodSync(config, mode);
      const lines = [];
      resolveSettings({ env, cwd: w.dir, filePath: 'docs/x.planer.json', warn: (line) => lines.push(line) });
      return lines;
    };
    const withToken = { url: 'https://x', token: 'geheim' };

    const lines = warningsFor(withToken, 0o644);
    assert.equal(lines.length, 1);
    assert.ok(lines[0].includes(`chmod 600 ${config}`), lines[0]);
    assert.doesNotMatch(lines[0], /geheim/);
    assert.equal(warningsFor(withToken, 0o640).length, 1, 'auch für die Gruppe');

    assert.deepEqual(warningsFor(withToken, 0o600), []);
    assert.deepEqual(warningsFor(withToken, 0o400), []);
    assert.deepEqual(warningsFor({ url: 'https://x' }, 0o644, { MOLLO_PLANER_TOKEN: 'aus-env' }), [], 'ohne Zugang in der Datei gibt es nichts zu warnen');
  } finally {
    w.cleanup();
  }
});

test('pull --project behält die Rechte von .mollo-planer', { skip: process.platform === 'win32' }, async () => {
  const w = workspace();
  try {
    writeFileSync(join(w.dir, '.gitignore'), '.mollo-planer\n');
    const config = join(w.dir, '.mollo-planer');
    writeFileSync(config, JSON.stringify({ url: 'https://planer.example', token: 'bleibt' }));
    chmodSync(config, 0o600);
    const env = { MOLLO_PLANER_URL: '', MOLLO_PLANER_TOKEN: '', MOLLO_PLANER_PROJECT: '' };

    assert.equal(await runSync('pull', ['docs/x.planer.json', '--project', '12'], w.ctx(exportApp(1).fetch, env)), EXIT.OK);
    assert.equal(statSync(config).mode & 0o777, 0o600);
    assert.equal(JSON.parse(readFileSync(config, 'utf8')).projects['docs/x.planer.json'], 12);
    assert.deepEqual(w.err.filter((line) => /lesbar/.test(line)), []);
  } finally {
    w.cleanup();
  }
});

test('Konfiguration: fehlende Adresse oder fehlender Zugang endet mit Exit 1', async () => {
  const w = workspace();
  try {
    const app = exportApp(1);
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch, { MOLLO_PLANER_URL: '' })), EXIT.USAGE);
    assert.match(w.err.join('\n'), /Adresse der App/);
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch, { MOLLO_PLANER_TOKEN: '' })), EXIT.USAGE);
    assert.equal(await runSync('pull', ['docs/x.planer.json', '--token', 'x'], w.ctx(app.fetch)), EXIT.USAGE);
    assert.match(w.err.join('\n'), /Unbekannte Option: --token/);
    assert.equal(app.calls.length, 0);
  } finally {
    w.cleanup();
  }
});

// ─── Fehlertexte, ohne Zugang in der Ausgabe ─────────────────────────

test('Fehler: 401, 403, 429 und Netz geben klare Texte mit Exit 6 und nie den Zugang aus', async () => {
  const cases = [
    [{ status: 401 }, /Zugang ungültig oder abgelaufen\. Neuer Zugang: Profil › Zugang für Claude\./],
    [{ status: 403 }, /Zugang ungültig oder abgelaufen/],
    [{ status: 429 }, /Zu viele Anfragen\. In einer Minute erneut versuchen\./],
    [{ failWith: Object.assign(new TypeError('fetch failed'), { cause: { code: 'ECONNREFUSED' } }) }, /Keine Verbindung zu https:\/\/planer\.example: ECONNREFUSED/],
    [{ failWith: Object.assign(new Error('Zeit'), { name: 'TimeoutError' }) }, /Zeitüberschreitung nach 30 s bei https:\/\/planer\.example/],
  ];
  for (const [options, pattern] of cases) {
    const w = workspace();
    try {
      const app = fakeApp(options);
      assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.REMOTE);
      assert.match(w.err.join('\n'), pattern);
      assert.doesNotMatch(w.err.join('\n') + w.out.join('\n'), new RegExp(SECRET));
    } finally {
      w.cleanup();
    }
  }
});

test('MCP-Client: schickt den Zugang als Bearer, initialisiert einmal und übernimmt die Sitzung', async () => {
  const seen = [];
  const fetch = async (url, options) => {
    const body = JSON.parse(options.body);
    seen.push({ method: body.method, headers: options.headers });
    const headers = body.method === 'initialize' ? { 'Mcp-Session-Id': 'abc' } : {};
    const result = body.method === 'initialize' ? {} : { content: [{ type: 'text', text: '{"ok":true}' }], isError: false };
    return Response.json({ jsonrpc: '2.0', id: body.id, result }, { headers });
  };
  const client = new McpClient({ url: 'https://x', token: SECRET, fetch });
  await client.call('export_plan', {});
  await client.call('export_plan', {});

  assert.deepEqual(seen.map((call) => call.method), ['initialize', 'tools/call', 'tools/call']);
  assert.equal(seen[1].headers.Authorization, `Bearer ${SECRET}`);
  assert.equal(seen[1].headers['Mcp-Session-Id'], 'abc');
});

// ─── pull ────────────────────────────────────────────────────────────

test('pull: legt die Datei an, danach „unverändert“ ohne die Datei anzufassen', async () => {
  const w = workspace();
  try {
    const app = exportApp(3);
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(readFileSync(w.file, 'utf8'), canonicalText(plan(3)));
    assert.match(w.out[0], /Angelegt.*Revision 3/);

    const old = new Date('2020-01-01');
    utimesSync(w.file, old, old);
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(w.out.at(-1), 'unverändert (Revision 3)');
    assert.equal(statSync(w.file).mtime.getTime(), old.getTime());
  } finally {
    w.cleanup();
  }
});

test('pull: neue Revision wird mit „Revision A → B“ geschrieben, das $schema der Datei bleibt', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText({ $schema: 'planer-v2.schema.json', ...plan(3) }));
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(exportApp(5).fetch)), EXIT.OK);
    assert.equal(w.out.at(-1), 'Revision 3 → 5');
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).$schema, 'planer-v2.schema.json');
  } finally {
    w.cleanup();
  }
});

test('pull: bei „changed“ Abbruch mit Exit 3, --force verwirft die Änderungen', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = exportApp(3);

    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.LOCAL_CHANGES);
    assert.match(w.err.join('\n'), /Lokale Änderungen\. Zuerst `push` oder `sync`, oder `pull --force` \(verwirft sie\)\./);
    assert.equal(app.calls.length, 0);
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).changed, true);

    assert.equal(await runSync('pull', ['docs/x.planer.json', '--force'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).changed, undefined);
  } finally {
    w.cleanup();
  }
});

test('pull --project trägt die Zuordnung in eine vorhandene .mollo-planer ein und lässt den Zugang unberührt', async () => {
  const w = workspace();
  try {
    writeFileSync(join(w.dir, '.gitignore'), '.mollo-planer\n');
    writeFileSync(join(w.dir, '.mollo-planer'), JSON.stringify({ url: 'https://planer.example', token: 'bleibt' }));
    const env = { MOLLO_PLANER_URL: '', MOLLO_PLANER_TOKEN: '', MOLLO_PLANER_PROJECT: '' };
    assert.equal(await runSync('pull', ['docs/x.planer.json', '--project', '12'], w.ctx(exportApp(1).fetch, env)), EXIT.OK);
    assert.deepEqual(JSON.parse(readFileSync(join(w.dir, '.mollo-planer'), 'utf8')), { url: 'https://planer.example', token: 'bleibt', projects: { 'docs/x.planer.json': 12 } });

    // Danach genügt der Pfad.
    assert.equal(await runSync('pull', ['docs/x.planer.json'], w.ctx(exportApp(1).fetch, env)), EXIT.OK);
  } finally {
    w.cleanup();
  }
});

// ─── push ────────────────────────────────────────────────────────────

const importOk = (changes, document) => ({ data: { ok: true, changes, errors: [], idMap: {}, document } });

test('push: ohne „changed“ nichts einzutragen, ohne Anfrage an die App', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3)));
    const app = fakeApp();
    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(w.out[0], 'Nichts einzutragen.');
    assert.equal(app.calls.length, 0);
  } finally {
    w.cleanup();
  }
});

test('push: Trockenlauf, dann echter Import, schreibt echte IDs und die neue Revision ohne „changed“', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const after = plan(4);
    const app = fakeApp({
      handlers: {
        import_plan: (args) => importOk([{ kind: 'CREATE', path: 'milestones[0]', id: args.dry_run ? 'm-new-1' : 'm-9', fields: ['title'] }], after),
      },
    });

    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.deepEqual(app.calls.filter((call) => call.name === 'import_plan').map((call) => [call.args.dry_run, call.args.mode, call.args.notify_assignees]), [[true, 'full', false], [false, 'full', false]]);
    assert.match(w.out.join('\n'), /1 Änderung: 1 neu/);
    assert.equal(readFileSync(w.file, 'utf8'), canonicalText(after));
  } finally {
    w.cleanup();
  }
});

test('push: Revision der App weicht ab, Exit 4 mit beiden Zahlen und ohne echten Import', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = fakeApp({
      handlers: {
        import_plan: () => ({ isError: true, data: { ok: false, errors: [{ path: 'revision', kind: 'revision', message: 'Die Datei hat die Revision 3, das Projekt ist bei 8.' }] } }),
        export_plan: () => ({ data: { document: plan(8) } }),
      },
    });

    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.REVISION);
    assert.match(w.err.join('\n'), /Die App ist bei Revision 8, die Datei bei 3\./);
    assert.match(w.err.join('\n'), /Zuerst `pull`/);
    assert.equal(app.calls.filter((call) => call.name === 'import_plan').length, 1);
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).changed, true);
  } finally {
    w.cleanup();
  }
});

test('push: Löschungen ohne --yes enden mit Exit 5 und nennen die Titel, mit --yes wird eingetragen', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const deletion = [{ kind: 'DELETE', path: 'k-7', id: 'k-7', fields: [], title: 'Alter Task' }];
    const app = fakeApp({ handlers: { import_plan: () => importOk(deletion, plan(4)) } });

    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.DELETIONS);
    assert.match(w.err.join('\n'), /Alter Task/);
    assert.equal(app.calls.filter((call) => call.name === 'import_plan' && !call.args.dry_run).length, 0);

    assert.equal(await runSync('push', ['docs/x.planer.json', '--yes'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).revision, 4);
  } finally {
    w.cleanup();
  }
});

test('push: Fehler der App mit Pfad, Exit 2, nichts geschrieben', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = fakeApp({ handlers: { import_plan: () => ({ isError: true, data: { ok: false, errors: [{ path: 'milestones[0].title', kind: 'denied', message: 'Dafür fehlt dir das Recht.' }] } }) } });

    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.INVALID);
    assert.match(w.err.join('\n'), /milestones\[0\]\.title: Dafür fehlt dir das Recht\./);
  } finally {
    w.cleanup();
  }
});

test('push: eine ungültige Datei wird lokal abgelehnt, ohne Anfrage', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, JSON.stringify({ format: 'planer-v2', changed: true }));
    const app = fakeApp();
    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.INVALID);
    assert.equal(app.calls.length, 0);
  } finally {
    w.cleanup();
  }
});

test('push ohne Änderungen im Trockenlauf entfernt nur „changed“', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = fakeApp({ handlers: { import_plan: () => importOk([], plan(3)) } });
    assert.equal(await runSync('push', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.equal(readFileSync(w.file, 'utf8'), canonicalText(plan(3)));
    assert.equal(app.calls.filter((call) => call.name === 'import_plan').length, 1);
  } finally {
    w.cleanup();
  }
});

test('push --partial: prüft die Revision selbst und verlangt dann --force', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = fakeApp({ handlers: { export_plan: () => ({ data: { document: plan(9) } }), import_plan: () => importOk([{ kind: 'UPDATE', path: 'milestones[0]', id: 'm-1', fields: ['title'] }], plan(10)) } });

    assert.equal(await runSync('push', ['docs/x.planer.json', '--partial'], w.ctx(app.fetch)), EXIT.REVISION);
    assert.match(w.err.join('\n'), /--force/);
    assert.equal(app.calls.filter((call) => call.name === 'import_plan').length, 0);

    assert.equal(await runSync('push', ['docs/x.planer.json', '--partial', '--force'], w.ctx(app.fetch)), EXIT.OK);
    assert.deepEqual(app.calls.filter((call) => call.name === 'import_plan').map((call) => call.args.mode), ['partial', 'partial']);
  } finally {
    w.cleanup();
  }
});

// ─── sync und watch ──────────────────────────────────────────────────

test('sync: mit „changed“ erst push, dann pull; sonst pull', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = fakeApp({ handlers: { import_plan: () => importOk([{ kind: 'UPDATE', path: 'milestones[0]', id: 'm-1', fields: ['title'] }], plan(4)), export_plan: () => ({ data: { document: plan(4) } }) } });

    assert.equal(await runSync('sync', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.deepEqual(app.calls.filter((call) => call.method === 'tools/call').map((call) => call.name), ['import_plan', 'import_plan', 'export_plan']);
    assert.equal(w.out.at(-1), 'unverändert (Revision 4)');

    app.calls.length = 0;
    assert.equal(await runSync('sync', ['docs/x.planer.json'], w.ctx(app.fetch)), EXIT.OK);
    assert.deepEqual(app.calls.filter((call) => call.method === 'tools/call').map((call) => call.name), ['export_plan']);
  } finally {
    w.cleanup();
  }
});

/** Ein Kontext, der nach n Runden anhält; `sleeps` merkt sich die Abstände. */
function watching(w, fetch, rounds) {
  const sleeps = [];
  const ctx = w.ctx(fetch);
  ctx.stopped = () => sleeps.length >= rounds;
  ctx.sleep = async (ms) => { sleeps.push(ms); };
  return { ctx, sleeps };
}

test('pull --watch: zwei Runden, Abstand mindestens 10 s, schreibt bei neuer Revision', async () => {
  const w = workspace();
  try {
    let revision = 1;
    const app = fakeApp({ handlers: { export_plan: () => ({ data: { document: plan(revision++) } }) } });
    const { ctx, sleeps } = watching(w, app.fetch, 2);

    assert.equal(await runSync('pull', ['docs/x.planer.json', '--watch', '--interval', '3'], ctx), EXIT.OK);
    assert.deepEqual(sleeps, [10_000, 10_000]);
    assert.equal(JSON.parse(readFileSync(w.file, 'utf8')).revision, 2);
    assert.equal(w.out.filter((line) => line.startsWith('unverändert')).length, 0);
  } finally {
    w.cleanup();
  }
});

test('pull --watch: bei „changed“ nur einmal ein Hinweis, die Datei bleibt', async () => {
  const w = workspace();
  try {
    writeFileSync(w.file, canonicalText(plan(3, { changed: true })));
    const app = exportApp(5);
    const { ctx } = watching(w, app.fetch, 3);

    assert.equal(await runSync('pull', ['docs/x.planer.json', '--watch'], ctx), EXIT.OK);
    assert.equal(w.out.filter((line) => /Lokale Änderungen/.test(line)).length, 1);
    assert.equal(app.calls.length, 0);
  } finally {
    w.cleanup();
  }
});

test('pull --watch: 429 und Netzfehler geben die nächste Runde, ein ungültiger Zugang beendet mit Exit 6', async () => {
  const w = workspace();
  try {
    const answers = [429, 200, 401];
    const fetch = async (url, options) => {
      const body = JSON.parse(options.body);
      if (body.method === 'initialize') {
        const status = answers.shift();
        return status === 200 ? Response.json({ jsonrpc: '2.0', id: 1, result: {} }) : new Response('{}', { status });
      }
      return Response.json({ jsonrpc: '2.0', id: 2, result: { content: [{ type: 'text', text: JSON.stringify({ document: plan(1) }) }], isError: false } });
    };
    const { ctx } = watching(w, fetch, 10);

    assert.equal(await runSync('pull', ['docs/x.planer.json', '--watch'], ctx), EXIT.REMOTE);
    assert.match(w.err.join('\n'), /Zu viele Anfragen.*Nächster Versuch/);
    assert.match(w.err.join('\n'), /Zugang ungültig/);
    assert.ok(existsSync(w.file));
  } finally {
    w.cleanup();
  }
});
