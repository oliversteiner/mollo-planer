import { execFileSync } from 'node:child_process'
import { closeSync, openSync } from 'node:fs'
import { backendEnv, DATABASE } from './environment'

/** A fresh database for every run: migrate:fresh with the accounts of the tests. Never the development database. */
export default function globalSetup(): void {
  if (!DATABASE.includes('mollo-planer-e2e')) {
    throw new Error('The database of the browser tests must be its own file.')
  }

  closeSync(openSync(DATABASE, 'w'))
  execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--seeder=E2eSeeder', '--force', '--no-interaction'], {
    cwd: '../backend',
    env: { ...process.env, ...backendEnv },
    stdio: 'pipe'
  })
}
