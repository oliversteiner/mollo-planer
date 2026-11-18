#!/usr/bin/env node
/**
 * Startet die App auf einer eigenen SQLite-Datei (php artisan serve), setzt die Demo-Daten auf, erzeugt Zugänge
 * mit der Fähigkeit `mcp` und lässt test/remote-integration.test.mjs laufen (docs/auftrag-m5.md §2.3).
 * Nichts davon berührt die Entwicklungsdatenbank. Braucht PHP und die installierten Pakete in backend/.
 */
import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const backend = fileURLToPath(new URL('../../../backend', import.meta.url));
const tools = fileURLToPath(new URL('..', import.meta.url));
const work = mkdtempSync(join(tmpdir(), 'planer-remote-app-'));
const database = join(work, 'app.sqlite');
writeFileSync(database, '');

// Without backend/.env (CI) there is no app key: a throwaway one, only for this run.
const appKey = process.env.APP_KEY || `base64:${randomBytes(32).toString('base64')}`;
const env = { ...process.env, APP_KEY: appKey, APP_ENV: 'local', DB_CONNECTION: 'sqlite', DB_DATABASE: database, MAIL_MAILER: 'array', CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync' };
const php = (...args) => spawnSync('php', ['artisan', ...args], { cwd: backend, env, encoding: 'utf8' });

function freePort() {
  return new Promise((resolve, reject) => {
    const server = createServer();
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      server.close(() => resolve(port));
    });
    server.on('error', reject);
  });
}

let server = null;
const cleanup = () => {
  server?.kill();
  rmSync(work, { recursive: true, force: true });
};
process.on('exit', cleanup);
process.on('SIGINT', () => process.exit(130));

const seeded = php('migrate:fresh', '--seed', '--seeder=DemoSeeder', '--no-interaction');
if (seeded.status !== 0) {
  console.error(`Die Demo-Daten konnten nicht aufgesetzt werden:\n${seeded.stdout}${seeded.stderr}`);
  process.exit(1);
}

// Ein Zugang je Person: die App begrenzt auf 60 Anfragen je Minute und Person.
const tokens = ['anna.keller', 'ben.mueller', 'chiara.rossi'].map((name) => {
  const code = `echo App\\Support\\McpTokens::create(App\\Models\\User::where("email", "${name}@example.ch")->firstOrFail(), "remote-test")->plainTextToken;`;
  const result = php('tinker', '--execute', code);
  const token = result.stdout.trim().split('\n').pop();
  if (!/^\d+\|/.test(token)) {
    console.error(`Kein Zugang für ${name}:\n${result.stdout}${result.stderr}`);
    process.exit(1);
  }
  return token;
});

const port = await freePort();
server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], { cwd: backend, env, stdio: 'ignore' });

const url = `http://127.0.0.1:${port}`;
for (let attempt = 0; attempt < 50; attempt++) {
  try {
    if ((await fetch(`${url}/up`)).ok) {
      break;
    }
  } catch {
    // noch nicht bereit
  }
  await new Promise((resolve) => setTimeout(resolve, 200));
  if (attempt === 49) {
    console.error('Die App startet nicht.');
    process.exit(1);
  }
}

const result = spawnSync(process.execPath, ['--test', 'test/remote-integration.test.mjs'], {
  cwd: tools,
  stdio: 'inherit',
  env: { ...process.env, MOLLO_PLANER_TEST_URL: url, MOLLO_PLANER_TEST_TOKEN: tokens.join(',') },
});
process.exit(result.status ?? 1);
