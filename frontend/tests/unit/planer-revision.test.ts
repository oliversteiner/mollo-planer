import { describe, expect, it } from 'vitest'
import { isForeignChange } from '../../app/utils/planer-revision'

describe('isForeignChange (S3f)', () => {
  it('takes one step after an own change as ours', () => {
    expect(isForeignChange(4, 5, true)).toBe(false)
    expect(isForeignChange(4, 4, true)).toBe(false)
  })

  it('sees more than one step after an own change as someone else\'s change', () => {
    expect(isForeignChange(4, 6, true)).toBe(true)
  })

  it('sees every step found by the polling as someone else\'s change', () => {
    expect(isForeignChange(4, 5, false)).toBe(true)
    expect(isForeignChange(4, 4, false)).toBe(false)
  })

  it('never reports a change while a value is unknown', () => {
    expect(isForeignChange(null, 9, false)).toBe(false)
    expect(isForeignChange(3, null, true)).toBe(false)
  })
})
