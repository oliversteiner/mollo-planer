import { describe, expect, it } from 'vitest'
import { formatDate, isOverdue, today } from '../../app/utils/dates'

const evening = new Date(2026, 10, 8, 23, 30)

describe('dates', () => {
  it('takes today from the local calendar, also late in the evening', () => {
    expect(today(evening)).toBe('2026-11-08')
  })

  it('is overdue only before today', () => {
    expect(isOverdue('2026-11-07', evening)).toBe(true)
    expect(isOverdue('2026-11-08', evening)).toBe(false)
    expect(isOverdue('2026-12-24', evening)).toBe(false)
    expect(isOverdue(null, evening)).toBe(false)
  })

  it('formats as a calendar day without shifting by the time zone', () => {
    expect(formatDate('2026-12-24', 'de-CH')).toBe('24.12.2026')
    expect(formatDate('2027-01-01', 'en-GB')).toBe('01/01/2027')
  })
})
