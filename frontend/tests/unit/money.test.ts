import { describe, expect, it } from 'vitest'
import { MAX_CENTS, formatMoney, parseMoney } from '../../app/utils/money'

describe('money of the billing', () => {
  it('shows Rappen as francs with the separators of the language', () => {
    expect(formatMoney(150050)).toBe('1’500.50')
    expect(formatMoney(MAX_CENTS)).toBe('9’999’999.99')
    expect(formatMoney(5)).toBe('0.05')
    expect(formatMoney(150000, 'en')).toBe('1,500.00')
  })

  it('reads what people type, in whole Rappen', () => {
    expect(parseMoney('1500')).toBe(150000)
    expect(parseMoney('1’500.5')).toBe(150050)
    expect(parseMoney('1\'500,50')).toBe(150050)
    expect(parseMoney('1 500.00')).toBe(150000)
    expect(parseMoney('1,500.00')).toBe(150000)
    expect(parseMoney('0.07')).toBe(7)
    expect(parseMoney('')).toBeNull()
  })

  it('refuses what is no amount', () => {
    expect(parseMoney('12.345')).toBeNaN()
    expect(parseMoney('-5')).toBeNaN()
    expect(parseMoney('zehn')).toBeNaN()
  })

  it('never goes through a float that loses a Rappen', () => {
    expect(parseMoney('0.29')).toBe(29)
    expect(parseMoney('9999999.99')).toBe(MAX_CENTS)
  })
})
