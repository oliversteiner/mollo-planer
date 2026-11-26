import { describe, expect, it } from 'vitest'
import { priceBadge, priceSums } from '../../app/utils/milestone-price'

const invoice = (status: string, number: string | null = null) => ({ invoice: { id: '7', number, status } })

describe('priceBadge: the five cases of docs/ux-projektseite.md §4.4', () => {
  it('a price and no invoice', () => {
    expect(priceBadge({ status: 'OPEN', priceCents: 780000 })).toEqual({ kind: 'price', cents: 780000 })
  })

  it('a price and an invoice in draft', () => {
    expect(priceBadge({ status: 'DONE', priceCents: 620000, billing: invoice('DRAFT') })).toEqual({ kind: 'draft', cents: 620000, invoiceId: '7' })
  })

  it('a price and an invoice that is issued or paid', () => {
    expect(priceBadge({ status: 'DONE', priceCents: 480000, billing: invoice('ISSUED', '2026-0007') })).toEqual({ kind: 'billed', cents: 480000, invoiceId: '7', number: '2026-0007' })
    expect(priceBadge({ status: 'DONE', priceCents: 480000, billing: invoice('PAID', '2026-0007') })?.kind).toBe('billed')
  })

  it('no price on a done milestone that is not billed: a hint', () => {
    expect(priceBadge({ status: 'DONE', priceCents: null, billing: null })).toEqual({ kind: 'noPrice' })
  })

  it('no price on a milestone that is not done: nothing', () => {
    expect(priceBadge({ status: 'OPEN', priceCents: null })).toBeNull()
    expect(priceBadge({ status: 'IN_PROGRESS' })).toBeNull()
  })

  it('without billing.manage the missing price of a done milestone is no hint (the API gives no price to anybody then)', () => {
    expect(priceBadge({ status: 'DONE', priceCents: null, billing: null }, false)).toBeNull()
  })

  it('a price of 0 is a price', () => {
    expect(priceBadge({ status: 'DONE', priceCents: 0 })).toEqual({ kind: 'price', cents: 0 })
  })
})

describe('priceSums', () => {
  it('counts only milestones with a price, and splits billed and draft', () => {
    expect(priceSums([
      { status: 'DONE', priceCents: 480000, billing: invoice('ISSUED', '2026-0007') },
      { status: 'DONE', priceCents: 620000, billing: invoice('DRAFT') },
      { status: 'OPEN', priceCents: 1490000 },
      { status: 'DONE', priceCents: null },
      { status: 'OPEN' }
    ])).toEqual({ total: 2590000, billed: 480000, draft: 620000 })
  })

  it('is null without a single price, also for a hint only', () => {
    expect(priceSums([])).toBeNull()
    expect(priceSums([{ status: 'DONE', priceCents: null }, { status: 'OPEN' }])).toBeNull()
  })
})
