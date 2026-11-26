import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { LETTERHEAD_GUIDE_URL } from '../../app/utils/letterhead-guide'

const source = fileURLToPath(new URL('../../../docs/briefpapier/briefpapier-anleitung.pdf', import.meta.url))
const published = fileURLToPath(new URL(`../../public${LETTERHEAD_GUIDE_URL}`, import.meta.url))

describe('guide to the letterhead', () => {
  it('is served as the same file as in docs/briefpapier', () => {
    expect(readFileSync(published).equals(readFileSync(source))).toBe(true)
  })

  it('has a date in its name, so no cache serves an old copy', () => {
    expect(LETTERHEAD_GUIDE_URL).toMatch(/^\/downloads\/briefpapier-anleitung-\d{4}-\d{2}-\d{2}\.pdf$/)
  })
})
