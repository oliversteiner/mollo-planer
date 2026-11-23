import { getIconLibrary } from '@web.awesome.me/webawesome-pro/dist/components/icon/library.js'
import { describe, expect, it } from 'vitest'

const duotone = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path opacity=".4" fill="currentColor" d="M0 0"/><path fill="currentColor" d="M1 1"/></svg>'

function parse(markup: string): SVGElement {
  const svg = new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement as unknown as SVGElement
  return svg
}

describe('icon libraries', () => {
  it('load the icons from our own server, for the app and for the components', async () => {
    const config = useRuntimeConfig()
    const base = `${config.app.baseURL}icons/${config.public.iconVersion}/${config.public.iconStyle}/`

    for (const name of ['default', 'system']) {
      const library = getIconLibrary(name)!
      expect(await library.resolver('rocket', 'classic', 'solid', false)).toBe(`${base}rocket.svg`)
    }
  })

  it('map system icons without a Duotone Light twin and refuse odd names', async () => {
    const config = useRuntimeConfig()
    const base = `${config.app.baseURL}icons/${config.public.iconVersion}/${config.public.iconStyle}/`
    const system = getIconLibrary('system')!

    expect(await system.resolver('indeterminate', 'classic', 'solid', false)).toBe(`${base}minus.svg`)
    expect(await getIconLibrary('default')!.resolver('../../etc', 'classic', 'solid', false)).toBe(`${base}circle-question.svg`)
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
