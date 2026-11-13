import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import de from '../../i18n/locales/de.json'
import en from '../../i18n/locales/en.json'

const enumsDir = fileURLToPath(new URL('../../../backend/app/Enums', import.meta.url))

/** Case names of the backend enums: the GraphQL names the page shows (for example PROJECTS_CREATE). */
function abilityNames(file: string): string[] {
  const source = readFileSync(`${enumsDir}/${file}`, 'utf8')

  return [...source.matchAll(/case (\w+) = '[^']+';/g)].map(match => match[1] ?? '')
}

describe('rights table texts', () => {
  const abilities = [...abilityNames('SystemAbility.php'), ...abilityNames('ProjectAbility.php')]

  it('lists the abilities of the backend enums', () => {
    expect(abilities.length).toBe(25)
    expect(readdirSync(enumsDir)).toContain('ProjectAbility.php')
  })

  for (const ability of abilities) {
    it(`has a German and English text for the ability ${ability}`, () => {
      expect((de.rights.abilities as Record<string, string>)[ability], ability).toBeTruthy()
      expect((en.rights.abilities as Record<string, string>)[ability], ability).toBeTruthy()
    })
  }
})
