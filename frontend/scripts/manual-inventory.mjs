// What the manual must cover (Auftrag 54 C), read from the code: the pages of the app (frontend/app/pages), the rights
// (backend/app/Enums/Right.php), the entries of the command palette (their ids) and the tools of the MCP server
// (backend/tests/Fixtures/mcp-tools-list.json, the list the server answers, kept by a backend test).
//
// `node scripts/manual-inventory.mjs` prints what no chapter mentions yet, in the form of handbuch/ohne-kapitel.txt.
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { buildManual, ROOT } from './manual.mjs'

/** Files below a folder, recursively. */
function files(dir, extension) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)

    return statSync(path).isDirectory() ? files(path, extension) : (name.endsWith(extension) ? [path] : [])
  })
}

/** Routes of the pages as the chapters write them: /projects/[id]/kanban, / for index.vue. */
export function pages() {
  const dir = join(ROOT, 'frontend/app/pages')

  return files(dir, '.vue').map((path) => {
    const route = `/${relative(dir, path).replace(/\.vue$/, '').replace(/(^|\/)index$/, '')}`

    return route === '/' ? '/' : route.replace(/\/$/, '')
  }).sort()
}

/** Values of the rights: task.create, users.manage, … */
export function rights() {
  const source = readFileSync(join(ROOT, 'backend/app/Enums/Right.php'), 'utf8')

  return [...source.matchAll(/case [A-Z_]+ = '([a-zA-Z.]+)';/g)].map(match => match[1]).sort()
}

/** Ids of the entries of the command palette: in utils/commands.ts, the palette itself and every useCommands(…) of a page. */
export function commands() {
  const app = join(ROOT, 'frontend/app')
  const sources = files(app, '.vue').concat(files(app, '.ts')).filter((path) => {
    const text = readFileSync(path, 'utf8')

    return text.includes('useCommands(') || path.endsWith('utils/commands.ts') || path.endsWith('CommandPalette.vue')
  })
  const ids = new Set()

  for (const path of sources) {
    for (const match of readFileSync(path, 'utf8').matchAll(/\bid: '([a-z][a-z0-9-]+)'/g)) {
      ids.add(match[1])
    }
  }

  return [...ids].sort()
}

/** Names of the tools of the MCP server. */
export function tools() {
  return JSON.parse(readFileSync(join(ROOT, 'backend/tests/Fixtures/mcp-tools-list.json'), 'utf8')).map(tool => tool.name).sort()
}

/** Everything as "kind name" lines: page /admin/users, right users.manage, command page-users, tool list_tasks. */
export function inventory() {
  return [
    ...pages().map(name => `page ${name}`),
    ...rights().map(name => `right ${name}`),
    ...commands().map(name => `command ${name}`),
    ...tools().map(name => `tool ${name}`)
  ]
}

/** What the chapters mention, as the same lines (from the German chapters; the English ones are kept equal by a test). */
export function mentioned(chapters = buildManual().chapters.de) {
  const lines = new Set()

  for (const chapter of chapters) {
    chapter.fields.pages.forEach(name => lines.add(`page ${name}`))
    chapter.fields.tools.forEach(name => lines.add(`tool ${name}`))
    chapter.fields.commands.forEach(name => lines.add(`command ${name}`))

    if (chapter.fields.right) {
      lines.add(`right ${chapter.fields.right}`)
    }
  }

  return lines
}

/** The lines of handbuch/ohne-kapitel.txt (comments and empty lines left out). */
export function withoutChapter() {
  return readFileSync(join(ROOT, 'handbuch/ohne-kapitel.txt'), 'utf8').split('\n').map(line => line.trim()).filter(line => line && !line.startsWith('#'))
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const done = mentioned()
  inventory().filter(line => !done.has(line)).forEach(line => console.log(line))
}
