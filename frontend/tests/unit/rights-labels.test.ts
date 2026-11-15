import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'

const enumFile = fileURLToPath(new URL('../../../backend/app/Enums/Right.php', import.meta.url))

/** Case names of the backend enum Right: the GraphQL names the page shows (for example PROJECTS_CREATE). */
function rightNames(): string[] {
  const source = readFileSync(enumFile, 'utf8')

  return [...source.matchAll(/case (\w+) = '[^']+';/g)].map(match => match[1] ?? '')
}

describe('rights table texts', () => {
  const rights = rightNames()

  it('lists the rights of the backend enum', () => {
    expect(rights.length).toBe(40)
    expect(rights).toContain('PROJECTS_CREATE')
  })

  for (const right of rights) {
    it(`has a German and English text for the right ${right}`, () => {
      expect((de.rights.names as Record<string, string>)[right], right).toBeTruthy()
      expect((en.rights.names as Record<string, string>)[right], right).toBeTruthy()
    })
  }
})
