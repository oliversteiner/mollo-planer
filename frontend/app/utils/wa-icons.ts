import { registerIconLibrary } from '@web.awesome.me/webawesome-pro/dist/webawesome.js'

/**
 * Icon libraries of Web Awesome: both `default` (icons of the app) and
 * `system` (icons inside the components: arrows, check mark, close, ...)
 * load Font Awesome Pro "Duotone Light" from our own server, the SVG files
 * that nitro serves under /icons/<version>/<style>/<name>.svg (nuxt.config.ts; Duotone Light from the private
 * repository of the Pro icons, else Font Awesome Free solid, see scripts/icon-source.mjs).
 * Nothing comes from the Font Awesome CDN.
 */

/** Names the components ask for in the system library that Duotone Light has under another name. */
const systemAliases: Record<string, string> = {
  'indeterminate': 'minus',
  'play-circle': 'circle-play'
}

/** Same format as the server rule FontAwesomeIconRule. */
export const iconNameFormat = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

/** Folder of the catalogue with the version: `${baseURL}icons/${iconVersion}/`; the SVGs are in its subfolder <style>. */
export function iconBaseUrl(baseURL: string, iconVersion: string): string {
  return `${baseURL}icons/${iconVersion}/`
}

export function iconUrl(iconBase: string, iconStyle: string, name: string): string {
  const file = iconNameFormat.test(name) ? name : 'circle-question'

  return `${iconBase}${iconStyle}/${file}.svg`
}

/**
 * Duotone SVGs have a secondary layer (path with an opacity, 40 %) and a
 * primary layer. Marking them lets the icon colour them apart with
 * --primary-color, --secondary-color, --primary-opacity and
 * --secondary-opacity (and swap-opacity); without these the icon is drawn in
 * the text colour with the 40 % layer, exactly as in the SVG.
 */
export function prepareDuotoneSvg(svg: SVGElement): void {
  if (!svg.hasAttribute('fill')) {
    svg.setAttribute('fill', 'currentColor')
  }

  const paths = [...svg.querySelectorAll('path')]
  const primary = paths.find(path => !path.hasAttribute('opacity'))
  const secondary = paths.find(path => path.hasAttribute('opacity'))

  if (primary && secondary && !svg.hasAttribute('data-duotone-initialized')) {
    primary.setAttribute('data-duotone-primary', '')
    secondary.setAttribute('data-duotone-secondary', '')
    svg.setAttribute('data-duotone-initialized', '')
  }
}

export function registerAppIcons(iconBase: string, iconStyle: string): void {
  registerIconLibrary('default', {
    resolver: name => iconUrl(iconBase, iconStyle, name),
    mutator: prepareDuotoneSvg
  })

  registerIconLibrary('system', {
    resolver: name => iconUrl(iconBase, iconStyle, systemAliases[name] ?? name),
    mutator: prepareDuotoneSvg
  })
}
