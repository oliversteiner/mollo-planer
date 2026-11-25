import { execFileSync } from 'node:child_process'
import { closeSync, mkdirSync, openSync, rmSync } from 'node:fs'
import { join } from 'node:path'
import { backendEnv, DATABASE, SCHEMA_CACHE, STORAGE } from './environment'

/** A fresh database for every run: migrate:fresh with the accounts of the tests. Never the development database. */
export default function globalSetup(): void {
  if (!DATABASE.includes('mollo-planer-e2e')) {
    throw new Error('The database of the browser tests must be its own file.')
  }

  if (!STORAGE.includes('mollo-planer-e2e')) {
    throw new Error('The storage of the browser tests must be its own folder.')
  }

  // A fresh storage folder with what Laravel expects in it (Auftrag 39: no file of the tests in backend/storage).
  rmSync(STORAGE, { recursive: true, force: true })
  for (const folder of ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) {
    mkdirSync(join(STORAGE, folder), { recursive: true })
  }

  // The backend of these tests must use the database file of the tests, whatever backend/.env says.
  const used = execFileSync('php', ['artisan', 'tinker', '--execute=echo "DB:" . config("database.default") . "|" . config("database.connections.sqlite.database");'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    encoding: 'utf8'
  }).split('DB:')[1]?.trim()

  const storage = execFileSync('php', ['artisan', 'tinker', '--execute=echo "DISK:" . config("filesystems.disks.local.root");'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    encoding: 'utf8'
  }).split('DISK:')[1]?.trim()

  if (storage !== join(STORAGE, 'app/private')) {
    throw new Error(`The browser tests would write files outside their own storage (${storage}). Nothing was started.`)
  }

  if (used !== `sqlite|${DATABASE}`) {
    throw new Error(`The browser tests would not use their own database (${used}). Nothing was started.`)
  }

  closeSync(openSync(DATABASE, 'w'))
  // The seeder refuses to run in production (on purpose), so only this step runs as "local". The server and the schema
  // cache below use the environment of the tests unchanged (production).
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--seeder=E2eSeeder', '--force', '--no-interaction'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv, APP_ENV: 'local' },
    stdio: 'pipe'
  })

  // Like deploy.sh: rebuild the compiled schema from the current code before the first request.
  rmSync(SCHEMA_CACHE, { force: true })
  execFileSync('php', ['artisan', 'lighthouse:cache', '--no-interaction'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    stdio: 'pipe'
  })
}
