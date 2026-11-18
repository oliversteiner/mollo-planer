import { describe, expect, it } from 'vitest'
import { matchesSearch, normalizeText } from '../../app/utils/text-search'

/** The person selection (PersonSelect) filters names with this search. */
describe('search in names', () => {
  it('ignores case and accents', () => {
    expect(normalizeText('Müller Zoë')).toBe('muller zoe')
    expect(matchesSearch('muller', ['Anna Müller'])).toBe(true)
    expect(matchesSearch('ZOE', ['Zoë Frei'])).toBe(true)
  })

  it('finds a part of the name and needs every word', () => {
    expect(matchesSearch('ben', ['Ben Owner'])).toBe(true)
    expect(matchesSearch('own', ['Ben Owner'])).toBe(true)
    expect(matchesSearch('ben chiara', ['Ben Owner'])).toBe(false)
  })

  it('shows everybody while nothing is typed', () => {
    expect(matchesSearch('', ['Ben Owner'])).toBe(true)
    expect(matchesSearch('   ', ['Ben Owner'])).toBe(true)
  })
})
