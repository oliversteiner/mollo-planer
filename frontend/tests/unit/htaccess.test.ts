import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const file = readFileSync(fileURLToPath(new URL('../../public/.htaccess', import.meta.url)), 'utf8')

/** The cache headers of the static frontend (Auftrag 36 A); the answer of a real Apache was checked by hand. */
describe('public/.htaccess', () => {
  it('caches the hashed build files for a year and the icons for 30 days, and never the pages', () => {
    expect(file).toContain('m#^/_nuxt/#')
    expect(file).toContain('public, max-age=31536000, immutable')
    expect(file).toContain('m#^/icons/#')
    expect(file).toContain('public, max-age=2592000"')
    expect(file).toContain('Header set Cache-Control "no-cache"')
  })

  it('asks for every module it needs, so a server without one does not answer with an error 500', () => {
    const directive = /^\s*(Header|AddOutputFilterByType)\b/gm
    const lines = file.split('\n')

    for (const [index, line] of lines.entries()) {
      if (!directive.test(line)) {
        directive.lastIndex = 0
        continue
      }
      directive.lastIndex = 0

      const before = lines.slice(0, index).join('\n')
      const openModules = [...before.matchAll(/<IfModule ([^>]+)>/g)].length - [...before.matchAll(/<\/IfModule>/g)].length

      expect(openModules, line).toBeGreaterThan(0)
    }

    expect(file).toMatch(/<IfModule mod_headers\.c>/)
    expect(file).toMatch(/<IfModule mod_filter\.c>/)
    expect(file).toMatch(/<IfModule mod_deflate\.c>/)
  })

  it('compresses the catalogue and the SVGs', () => {
    expect(file).toContain('AddOutputFilterByType DEFLATE application/json image/svg+xml')
  })
})
