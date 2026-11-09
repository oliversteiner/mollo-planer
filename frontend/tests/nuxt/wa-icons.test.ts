import { getIconLibrary } from '@web.awesome.me/webawesome-pro/dist/components/icon/library.js'
import { describe, expect, it } from 'vitest'

const duotone = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M0 0"/><path fill="currentColor" d="M1 1"/></svg>'

function parse(markup: string): SVGElement {
  const svg = new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement as unknown as SVGElement
  return svg
}

describe('icon libraries', () => {
  it('load Duotone Light from our own server, for the app and for the components', async () => {
    const base = useRuntimeConfig().app.baseURL

    for (const name of ['default', 'system']) {
      const library = getIconLibrary(name)!
      expect(await library.resolver('rocket', 'classic', 'solid', false)).toBe(`${base}icons/duotone-light/rocket.svg`)
    }
  })

  it('map system icons without a Duotone Light twin and refuse odd names', async () => {
    const base = useRuntimeConfig().app.baseURL
    const system = getIconLibrary('system')!

    expect(await system.resolver('indeterminate', 'classic', 'solid', false)).toBe(`${base}icons/duotone-light/minus.svg`)
    expect(await getIconLibrary('default')!.resolver('../../etc', 'classic', 'solid', false)).toBe(`${base}icons/duotone-light/circle-question.svg`)
  })

  it('mark the two layers of a duotone icon so they can be coloured apart', () => {
    const svg = parse(duotone)
    prepareDuotoneSvg(svg)

    expect(svg.querySelector('path[opacity]')!.hasAttribute('data-duotone-secondary')).toBe(true)
    expect(svg.querySelector('path:not([opacity])')!.hasAttribute('data-duotone-primary')).toBe(true)
  })

  it('leave an icon with a single layer alone', () => {
    const svg = parse('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>')
    prepareDuotoneSvg(svg)

    expect(svg.getAttribute('fill')).toBe('currentColor')
    expect(svg.querySelector('[data-duotone-primary]')).toBeNull()
  })
})
