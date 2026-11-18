import { execFileSync } from 'node:child_process'
import { closeSync, openSync } from 'node:fs'
import { backendEnv, DATABASE } from './environment'

/** A fresh database for every run: migrate:fresh with the accounts of the tests. Never the development database. */
export default function globalSetup(): void {
  if (!DATABASE.includes('mollo-planer-e2e')) {
    throw new Error('The database of the browser tests must be its own file.')
  }

  // The backend of these tests must use the database file of the tests, whatever backend/.env says.
  const used = execFileSync('php', ['artisan', 'tinker', '--execute=echo "DB:" . config("database.default") . "|" . config("database.connections.sqlite.database");'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    encoding: 'utf8'
  }).split('DB:')[1]?.trim()

  if (used !== `sqlite|${DATABASE}`) {
    throw new Error(`The browser tests would not use their own database (${used}). Nothing was started.`)
  }

  closeSync(openSync(DATABASE, 'w'))
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--seeder=E2eSeeder', '--force', '--no-interaction'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    stdio: 'pipe'
  })
}
