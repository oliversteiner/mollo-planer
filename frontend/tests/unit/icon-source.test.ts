import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
// @ts-expect-error plain JavaScript module of the scripts folder
import { buildCatalog, resolveIconSource } from '../../scripts/icon-source.mjs'

function root(options: { pro?: boolean, free?: boolean }): string {
  const dir = mkdtempSync(join(tmpdir(), 'icons-'))
  writeFileSync(join(dir, 'pro-assets.json'), JSON.stringify({ repository: 'example/pro-assets', ref: 'fontawesome-7.3.1', version: '7.3.1' }))

  if (options.pro) {
    mkdirSync(join(dir, 'vendor/fontawesome/7.3.1/duotone-light'), { recursive: true })
    writeFileSync(join(dir, 'vendor/fontawesome/7.3.1/catalog.json'), '{}')
  }

  if (options.free) {
    const free = join(dir, 'node_modules/@fortawesome/fontawesome-free')
    mkdirSync(join(free, 'svgs-full/solid'), { recursive: true })
    mkdirSync(join(free, 'metadata'), { recursive: true })
    writeFileSync(join(free, 'package.json'), JSON.stringify({ version: '7.3.1' }))
    writeFileSync(join(free, 'svgs-full/solid/rocket.svg'), '<svg></svg>')
    writeFileSync(join(free, 'metadata/icon-families.json'), JSON.stringify({ rocket: { label: 'Rocket', search: { terms: ['launch'] } } }))
  }

  return dir
}

describe('icon source', () => {
  it('uses the Pro icons when they are there', () => {
    const source = resolveIconSource(root({ pro: true, free: true }))

    expect(source).toMatchObject({ kind: 'pro', version: '7.3.1', style: 'duotone-light' })
    expect(source.assets).toHaveLength(1)
  })

  it('falls back to Font Awesome Free without the Pro icons, with a catalogue made from its metadata', () => {
    const source = resolveIconSource(root({ free: true }))

    expect(source).toMatchObject({ kind: 'free', version: '7.3.1-free', style: 'solid' })
    expect(source.assets.map((asset: { baseURL: string }) => asset.baseURL)).toEqual(['/icons/7.3.1-free/solid', '/icons/7.3.1-free'])
  })

  it('stops a deployment without the Pro icons instead of switching to Free silently', () => {
    expect(() => resolveIconSource(root({ free: true }), { requirePro: true })).toThrow(/Font Awesome Pro icons 7\.3\.1 are missing/)
  })

  it('builds catalogue rows with label and search terms, the name as label if there is no metadata', () => {
    expect(buildCatalog(['rocket', 'zzz'], { rocket: { label: 'Rocket', search: { terms: ['launch', 'space'] } } }))
      .toEqual([['rocket', 'Rocket', 'launch space'], ['zzz', 'zzz', '']])
  })
})
