import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { downloadName, planerMarkdown, readPlanerFile } from '../../app/utils/planer'

const fixture = (name: string) => readFileSync(fileURLToPath(new URL(`../../../spec/fixtures/${name}`, import.meta.url)), 'utf8')

describe('planer file', () => {
  it('reads a planer-v2 file', () => {
    const read = readPlanerFile(fixture('planer-valid.planer.json'))

    expect('document' in read && read.document.project.name).toBe('Mollo Planer')
  })

  it('says what is wrong with a file that is none', () => {
    expect(readPlanerFile('kaputt')).toEqual({ problem: 'notJson' })
    expect(readPlanerFile('{"format":"planer-v1"}')).toEqual({ problem: 'notPlaner' })
    expect(readPlanerFile('[]')).toEqual({ problem: 'notPlaner' })
  })

  it('names a download after the project', () => {
    expect(downloadName('Website-Relaunch', 'planer.json')).toBe('website-relaunch.planer.json')
    expect(downloadName('Sommerfest 2027 – Büro', 'md')).toBe('sommerfest-2027-buro.md')
    expect(downloadName('***', 'md')).toBe('projekt.md')
  })

  it('renders the same Markdown as tools/planer, in the language asked for', () => {
    const read = readPlanerFile(fixture('planer-valid.planer.json'))
    if (!('document' in read)) {
      throw new Error('fixture is not a planer file')
    }

    expect(planerMarkdown(read.document, 'de', false)).toBe(fixture('golden/planer-valid.md'))
    expect(planerMarkdown(read.document, 'en', true)).toContain('Members')
  })
})
