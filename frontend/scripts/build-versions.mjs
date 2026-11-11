#!/usr/bin/env node
/**
 * Writes app/data/versions.json from docs/entwicklung.md (format: mollo-version,
 * spec/format.md in the mollo-version repository):
 *
 * - the versions come from `mollo-version json`,
 * - the release notes come from the done tasks (feature → neu, design → verbessert, bug → behoben),
 * - the release date comes from the git tag vM.m.0 (the date of the commit it points to).
 *   Without a tag the date stays null.
 *
 * The result is committed, so the build does not need the mollo-version repository.
 *
 *   npm run versions
 *
 * The mollo-version CLI is read from ../mollo-version next to this repository, or from
 * the environment variable MOLLO_VERSION_BIN.
 */
import { execFileSync } from 'node:child_process'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const root = resolve(here, '../..')
const source = join(root, 'docs/entwicklung.md')
const output = join(here, '../app/data/versions.json')
const cli = process.env.MOLLO_VERSION_BIN ?? join(root, '../mollo-version/bin/mollo-version.mjs')

const KIND_TO_GROUP = { feature: 'neu', design: 'verbessert', bug: 'behoben' }

/** Commit date (YYYY-MM-DD) of the release tag, or null if the tag does not exist. */
function releaseDate(tag) {
  try {
    const date = execFileSync('git', ['-C', root, 'log', '-1', '--format=%cs', tag], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore']
    }).trim()
    return date || null
  } catch {
    return null
  }
}

const json = execFileSync(process.execPath, [cli, 'json', source], { encoding: 'utf8' })
const { milestones } = JSON.parse(json)

const versions = milestones.map((milestone) => {
  const tasks = milestone.todos.flatMap(todo => todo.tasks)
  const changes = tasks
    .filter(task => task.done && KIND_TO_GROUP[task.kind])
    .map(task => ({ kind: KIND_TO_GROUP[task.kind], text: task.title }))
  const released = milestone.status === 'veröffentlicht'

  return {
    major: milestone.major,
    minor: milestone.minor,
    number: milestone.number,
    title: milestone.title,
    description: milestone.description,
    status: milestone.status,
    releasedOn: released ? releaseDate(`v${milestone.major}.${milestone.minor}.0`) : null,
    changes
  }
})

mkdirSync(dirname(output), { recursive: true })
writeFileSync(output, `${JSON.stringify({ versions }, null, 2)}\n`)
console.log(`${versions.length} Versionen nach ${output} geschrieben.`)
