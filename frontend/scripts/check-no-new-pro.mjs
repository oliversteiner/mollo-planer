#!/usr/bin/env node
// Guard (Auftrag 81 E, "Pro läuft aus"): the app must not use NEW parts of Web Awesome Pro or Font Awesome Pro.
// Allowed is exactly what the free editions have (scripts/webawesome-free.json, the file list of
// @awesome.me/webawesome with its version; Font Awesome Free from node_modules) plus the fixed exception lists below,
// which hold the state of 26.11.2026. THE EXCEPTION LISTS MAY ONLY GET SHORTER: never add an entry, replace the Pro part
// with the free one (or with our own component, Auftrag 82) and remove the entry.
//
// Checked in the tracked files of frontend/ (app, plugins, nuxt.config.ts, scripts excluded):
//  - imports of `@web.awesome.me/webawesome-pro/dist/...` (components, styles, translations)
//  - tags `<wa-...>` (a Pro component is also caught when it is not imported yet)
//  - theme and palette classes `wa-theme-*`, `wa-palette-*`
//  - Font Awesome icon names that are written as literals (`name="x"`, `icon: 'x'`, ...); style is fixed, so only names count
import { execFileSync } from 'node:child_process'
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

/** Pro parts the app uses today. Remove an entry when the Pro part is gone, never add one. */
export const allowedPro = {
  components: ['combobox'],
  themes: ['matter'],
  palettes: ['mild'],
  styles: [],
  translations: [],
  /** Icon names without a Free counterpart (Duotone Light names). Generated 26.11.2026 from the code; only shorter. */
  icons: [
    'arrow-down-to-line',
    'arrow-left-to-line',
    'arrow-right-to-line',
    'arrow-up-to-line',
    'block-quote',
    'calendar-exclamation',
    'chevrons-down',
    'chevrons-up',
    'floppy-disk-circle-arrow-right',
    'h2',
    'h3',
    'horizontal-rule',
    'image-slash',
    'message-bot',
    'paper-plane-top',
    'share-from',
    'square-code',
    'sun-bright',
    'trash-can-xmark'
  ]
}

const importPattern = /@web\.awesome\.me\/webawesome-pro\/dist\/([A-Za-z0-9_./-]+)/g
const tagPattern = /<wa-([a-z0-9-]+)/g
const themePattern = /\bwa-theme-([a-z0-9-]+)/g
const palettePattern = /\bwa-palette-([a-z0-9-]+)/g

/** Names after `wa-theme-` that are no theme: the cascade layer `wa-theme-overrides` of our own CSS. */
const notThemeOrPalette = new Set(['overrides'])

/**
 * @param {string} text
 * @returns {string[]} icon names written as literals
 */
export function iconNamesIn(text) {
  const names = new Set()
  const add = (value) => {
    if (/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value)) {
      names.add(value)
    }
  }

  // <wa-icon name="x"> and :name="cond ? 'a' : 'b'" on a wa-icon
  for (const tag of text.matchAll(/<wa-icon\b[^>]*>/g)) {
    for (const match of tag[0].matchAll(/\sname="([^"]+)"/g)) {
      add(match[1])
    }

    for (const match of tag[0].matchAll(/:name="([^"]+)"/g)) {
      for (const literal of match[1].matchAll(/(?<![=!]==?\s*)'([a-z0-9-]+)'/g)) {
        add(literal[1])
      }
    }
  }

  // icon: 'x', icon="x", iconName: 'x'
  for (const match of text.matchAll(/(?<![-\w])(?:icon|iconName)\s*[:=]\s*['"]([a-z0-9-]+)['"]/g)) {
    add(match[1])
  }

  return [...names]
}

/**
 * @param {string} root the folder frontend/
 * @returns {Set<string> | null} names of the icons in Font Awesome Free (all styles), null when the package is missing
 */
export function freeIconNames(root) {
  const base = join(root, 'node_modules/@fortawesome/fontawesome-free/svgs-full')

  if (!existsSync(base)) {
    return null
  }

  const names = new Set()

  for (const style of readdirSync(base)) {
    for (const file of readdirSync(join(base, style))) {
      if (file.endsWith('.svg')) {
        names.add(file.slice(0, -4))
      }
    }
  }

  return names
}

/**
 * @param {{ file: string, text: string }[]} sources the files to check
 * @param {{ free: object, allowed?: typeof allowedPro, freeIcons?: Set<string> | null }} options
 * @returns {{ file: string, kind: string, name: string, hint: string }[]} every new use of Pro
 */
export function findNewPro(sources, { free, allowed = allowedPro, freeIcons = null }) {
  const problems = []
  const isFree = {
    components: name => free.components.includes(name),
    themes: name => free.themes.includes(name),
    palettes: name => free.palettes.includes(name),
    styles: name => free.styles.includes(name),
    translations: name => free.translations.includes(name)
  }
  const report = (file, kind, name, hint) => problems.push({ file, kind, name, hint })

  for (const { file, text } of sources) {
    for (const match of text.matchAll(importPattern)) {
      const path = match[1]
      let kind = null
      let name = null

      if (path.startsWith('components/')) {
        kind = 'components'
        name = path.split('/')[1]
      } else if (path.startsWith('styles/themes/')) {
        kind = 'themes'
        name = path.split('/')[2].replace(/\.css$/, '')
      } else if (path.startsWith('styles/color/palettes/')) {
        kind = 'palettes'
        name = path.split('/')[3].replace(/\.css$/, '')
      } else if (path.startsWith('styles/')) {
        kind = 'styles'
        name = path.split('/')[1].replace(/\.css$/, '')
      } else if (path.startsWith('translations/')) {
        kind = 'translations'
        name = path.split('/')[1].replace(/\.js$/, '')
      }

      if (kind && !isFree[kind](name) && !allowed[kind].includes(name)) {
        report(file, kind, name, `use the free edition (@awesome.me/webawesome ${free.version}) or an own component`)
      }
    }

    for (const match of text.matchAll(tagPattern)) {
      const name = match[1]

      if (!isFree.components(name) && !allowed.components.includes(name) && !/^icon$/.test(name)) {
        report(file, 'components', name, 'no free component of this name (see scripts/webawesome-free.json)')
      }
    }

    for (const match of text.matchAll(themePattern)) {
      const name = match[1]

      if (!notThemeOrPalette.has(name) && !isFree.themes(name) && !allowed.themes.includes(name)) {
        report(file, 'themes', name, 'no free theme of this name')
      }
    }

    for (const match of text.matchAll(palettePattern)) {
      const name = match[1]

      if (!notThemeOrPalette.has(name) && !isFree.palettes(name) && !allowed.palettes.includes(name)) {
        report(file, 'palettes', name, 'no free palette of this name')
      }
    }

    if (freeIcons) {
      for (const name of iconNamesIn(text)) {
        if (!freeIcons.has(name) && !allowed.icons.includes(name)) {
          report(file, 'icons', name, 'Font Awesome Free has no icon of this name; choose a free one')
        }
      }
    }
  }

  return problems
}

/**
 * @param {string} repoRoot the folder of the repository
 * @returns {{ file: string, text: string }[]} the tracked text files of frontend/ that hold app code
 */
export function appSources(repoRoot) {
  return execFileSync('git', ['ls-files', '-z', 'frontend/app', 'frontend/plugins', 'frontend/nuxt.config.ts'], { cwd: repoRoot })
    .toString('utf8')
    .split('\0')
    .filter(file => /\.(vue|ts|css|mjs)$/.test(file) && !file.endsWith('.d.ts'))
    .map(file => ({ file, text: readFileSync(join(repoRoot, file), 'utf8') }))
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const here = dirname(fileURLToPath(import.meta.url))
  const repoRoot = join(here, '../..')
  const free = JSON.parse(readFileSync(join(here, 'webawesome-free.json'), 'utf8'))
  const freeIcons = freeIconNames(join(here, '..'))

  if (!freeIcons) {
    console.warn('Font Awesome Free is not installed (npm ci): icon names are not checked.')
  }

  const problems = findNewPro(appSources(repoRoot), { free, freeIcons })

  if (problems.length > 0) {
    console.error('New use of Web Awesome Pro or Font Awesome Pro (Entscheid Oliver 26.11.2026: no new Pro):')
    problems.forEach(p => console.error(`  ${p.file}: ${p.kind} "${p.name}" – ${p.hint}`))
    console.error('The allowed Pro parts are the fixed list `allowedPro` in scripts/check-no-new-pro.mjs; it may only get shorter.')
    process.exit(1)
  }

  console.log('No new Pro parts are used.')
}
