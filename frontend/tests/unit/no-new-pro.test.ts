import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
// @ts-expect-error plain JavaScript module of the scripts folder
import { allowedPro, appSources, findNewPro, freeIconNames, iconNamesIn } from '../../scripts/check-no-new-pro.mjs'

const here = dirname(fileURLToPath(import.meta.url))
const free = JSON.parse(readFileSync(join(here, '../../scripts/webawesome-free.json'), 'utf8'))
const freeIcons = new Set(['rocket', 'lock'])

function check(text: string, icons: Set<string> | null = freeIcons) {
  return findNewPro([{ file: 'a.vue', text }], { free, freeIcons: icons })
}

describe('guard: no new Pro', () => {
  it('lets the free parts pass', () => {
    const text = `import '@web.awesome.me/webawesome-pro/dist/components/button/button.js'\n<wa-button><wa-icon name="rocket"></wa-icon></wa-button>`

    expect(check(text)).toEqual([])
  })

  it('lets the current exceptions pass', () => {
    const text = `import '@web.awesome.me/webawesome-pro/dist/components/combobox/combobox.js'\n@import '@web.awesome.me/webawesome-pro/dist/styles/themes/matter.css';\nclass: 'wa-theme-matter wa-palette-mild'\n<wa-combobox />`

    expect(check(text)).toEqual([])
  })

  it('fails on a new Pro component, imported or only used as a tag', () => {
    const imported = check(`import '@web.awesome.me/webawesome-pro/dist/components/data-grid/data-grid.js'`)
    const tag = check('<wa-sparkline></wa-sparkline>')

    expect(imported).toMatchObject([{ kind: 'components', name: 'data-grid' }])
    expect(tag).toMatchObject([{ kind: 'components', name: 'sparkline' }])
  })

  it('fails on a new Pro theme or palette', () => {
    const problems = check(`@import '@web.awesome.me/webawesome-pro/dist/styles/themes/glossy.css';\nclass: 'wa-theme-glossy wa-palette-vivid'`)

    expect(problems.map((problem: { kind: string, name: string }) => `${problem.kind}:${problem.name}`)).toEqual(
      expect.arrayContaining(['themes:glossy', 'palettes:vivid'])
    )
  })

  it('does not take the layer wa-theme-overrides for a theme', () => {
    expect(check('@layer wa-theme-overrides { a { color: red } }')).toEqual([])
  })

  it('fails on a new icon name without Font Awesome Free counterpart', () => {
    const problems = check(`<wa-icon name="hat-wizard-pro"></wa-icon>\n<wa-icon :name="x === 'lock' ? 'lock' : 'sparkles-pro'" />\nicon: 'rocket-pro'`)

    expect(problems.map((problem: { name: string }) => problem.name).sort()).toEqual(['hat-wizard-pro', 'rocket-pro', 'sparkles-pro'])
  })

  it('reads icon names as literals only, without comparisons and data attributes', () => {
    expect(iconNamesIn(`<wa-icon :name="kind === 'billed' ? 'file-invoice' : 'file-pen'" /> <i data-icon="name">`).sort()).toEqual(['file-invoice', 'file-pen'])
  })

  it('has fixed exception lists that never contain a free part', () => {
    expect(allowedPro.components.filter((name: string) => free.components.includes(name))).toEqual([])
    expect(allowedPro.themes.filter((name: string) => free.themes.includes(name))).toEqual([])
    expect(allowedPro.palettes.filter((name: string) => free.palettes.includes(name))).toEqual([])
  })

  it('finds no new Pro in the app as it is', () => {
    const root = join(here, '../../..')

    expect(findNewPro(appSources(root), { free, freeIcons: freeIconNames(join(here, '../..')) })).toEqual([])
  })
})
