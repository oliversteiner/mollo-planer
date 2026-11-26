/**
 * The price of a milestone on the project page (docs/ux-projektseite.md §4.4). Only with the right «billing.manage» the
 * API fills `priceCents` and `billing`; for everyone else both are null, so nothing here checks a right.
 */
export interface MilestonePriceInput {
  status: string
  priceCents?: number | null
  billing?: { invoice: { id: string, number: string | null, status: string } } | null
}

export type PriceBadge
  /** A price, no invoice. */
  = | { kind: 'price', cents: number }
  /** A price, the invoice is a draft. */
    | { kind: 'draft', cents: number, invoiceId: string }
  /** A price, the invoice is issued or paid. */
    | { kind: 'billed', cents: number, invoiceId: string, number: string | null }
  /** Done, billable, but no price yet: a hint. */
    | { kind: 'noPrice' }

/**
 * What the badge of a milestone shows; null: nothing (no price and not done, or already billed without a price).
 * `canBill`: the viewer has billing.manage. Only then the missing price of a done milestone is a hint: for everyone else
 * the API gives no price either, and «Ohne Preis» would be wrong.
 */
export function priceBadge(milestone: MilestonePriceInput, canBill = true): PriceBadge | null {
  const cents = milestone.priceCents ?? null
  const invoice = milestone.billing?.invoice ?? null

  if (cents !== null) {
    if (invoice === null) {
      return { kind: 'price', cents }
    }

    return invoice.status === 'DRAFT'
      ? { kind: 'draft', cents, invoiceId: invoice.id }
      : { kind: 'billed', cents, invoiceId: invoice.id, number: invoice.number }
  }

  return canBill && milestone.status === 'DONE' && invoice === null ? { kind: 'noPrice' } : null
}

export interface PriceSums {
  total: number
  billed: number
  draft: number
}

/** The sums of the page: only milestones with a price count; null when there is not a single price. */
export function priceSums(milestones: MilestonePriceInput[]): PriceSums | null {
  const sums: PriceSums = { total: 0, billed: 0, draft: 0 }
  let any = false

  for (const milestone of milestones) {
    const badge = priceBadge(milestone)

    if (badge === null || badge.kind === 'noPrice') {
      continue
    }

    any = true
    sums.total += badge.cents

    if (badge.kind === 'billed') {
      sums.billed += badge.cents
    } else if (badge.kind === 'draft') {
      sums.draft += badge.cents
    }
  }

  return any ? sums : null
}
