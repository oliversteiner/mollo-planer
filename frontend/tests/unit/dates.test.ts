import { afterAll, beforeAll, describe, expect, it } from 'vitest'
import { formatDate, formatDateTime, isAtOrAfter, isOverdue, today } from '../../app/utils/dates'

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
    expect(formatDate('2026-12-24', 'de-CH', true)).toBe('24. Dezember 2026')
    expect(formatDate('2027-01-01', 'en-GB', true)).toBe('1 January 2027')
  })
})

describe('points in time with their zone (F8.1)', () => {
  const original = process.env.TZ

  beforeAll(() => {
    process.env.TZ = 'Europe/Zurich'
  })

  afterAll(() => {
    if (original === undefined) {
      delete process.env.TZ
    } else {
      process.env.TZ = original
    }
  })

  it('shows 02:13 UTC as 03:13 in Zurich in winter and 04:13 in summer', () => {
    expect(formatDateTime('2026-11-18T02:13:49+00:00', 'de-CH')).toContain('03:13')
    expect(formatDateTime('2026-11-18T02:13:49Z', 'de-CH')).toContain('03:13')
    expect(formatDateTime('2026-07-18T02:13:49+00:00', 'de-CH')).toContain('04:13')
  })

  it('reads another zone as the same moment', () => {
    expect(formatDateTime('2026-11-18T03:13:49+01:00', 'de-CH')).toBe(formatDateTime('2026-11-18T02:13:49+00:00', 'de-CH'))
  })

  it('compares moments, not texts', () => {
    expect(isAtOrAfter('2026-11-18T03:00:00+01:00', '2026-11-18T02:00:00+00:00')).toBe(true)
    expect(isAtOrAfter('2026-11-18T03:00:00+01:00', '2026-11-18T02:30:00+00:00')).toBe(false)
    expect(isAtOrAfter('2026-11-18T02:00:00+00:00', '2026-11-18T02:00:00+00:00')).toBe(true)
  })
})
