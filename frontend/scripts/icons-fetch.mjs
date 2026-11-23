#!/usr/bin/env node
// Fetches the Font Awesome Pro icons (about 10 MB) from the private repository into frontend/vendor/fontawesome/<version>/.
// The folder is not in git (frontend/vendor/ is ignored): this repository is public.
//
//   npm run icons:fetch
//
// Which repository, tag and version: frontend/pro-assets.json.
// Locally the login of git/gh is used. In CI the environment variable PRO_ASSETS_TOKEN (read-only token for the one
// repository) is sent as a header and is not stored in the clone. PRO_ASSETS_URL replaces the address (for tests).
import { spawnSync } from 'node:child_process'
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const config = JSON.parse(readFileSync(join(root, 'pro-assets.json'), 'utf8'))
const target = join(root, 'vendor/fontawesome', config.version)

if (existsSync(join(target, 'duotone-light')) && existsSync(join(target, 'catalog.json'))) {
  console.log(`Font Awesome Pro ${config.version} is already in ${target}.`)
  process.exit(0)
}

const url = process.env.PRO_ASSETS_URL || `https://github.com/${config.repository}.git`
const token = process.env.PRO_ASSETS_TOKEN
const authentication = token
  ? ['-c', `http.extraheader=Authorization: Basic ${Buffer.from(`x-access-token:${token}`).toString('base64')}`]
  : []
const work = mkdtempSync(join(tmpdir(), 'pro-assets-'))
const clone = spawnSync('git', [...authentication, 'clone', '--quiet', '--depth', '1', '--branch', config.ref, url, work], {
  stdio: ['ignore', 'inherit', 'inherit'],
  env: { ...process.env, GIT_TERMINAL_PROMPT: '0' }
})

if (clone.status !== 0) {
  rmSync(work, { recursive: true, force: true })
  console.error(`Could not get ${config.repository} (${config.ref}). No access? Without the Pro icons the build uses Font Awesome Free.`)
  process.exit(1)
}

const source = join(work, 'fontawesome', config.version)

if (!existsSync(join(source, 'duotone-light'))) {
  rmSync(work, { recursive: true, force: true })
  console.error(`fontawesome/${config.version}/duotone-light is missing in ${config.repository} at ${config.ref}.`)
  process.exit(1)
}

rmSync(target, { recursive: true, force: true })
cpSync(source, target, { recursive: true })
rmSync(work, { recursive: true, force: true })
console.log(`Font Awesome Pro ${config.version} written to ${target}.`)
