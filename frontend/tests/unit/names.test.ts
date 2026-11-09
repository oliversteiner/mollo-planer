import { describe, expect, it } from 'vitest'
import { initials } from '../../app/utils/names'

describe('initials', () => {
  it('takes the first letters of the first and the last word', () => {
    expect(initials('Lindita Aliu')).toBe('LA')
    expect(initials('  Ada   Maria  Lovelace ')).toBe('AL')
  })

  it('works with one word, umlauts and nothing', () => {
    expect(initials('Madonna')).toBe('M')
    expect(initials('örs Ünal')).toBe('ÖÜ')
    expect(initials('')).toBe('')
    expect(initials(null)).toBe('')
  })
})
