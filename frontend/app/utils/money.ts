/**
 * Amounts of the billing (Auftrag 39): the API keeps whole Rappen (Int), never a float. Shown as 1’250.50 (de) or
 * 1,250.50 (en), like the PDF of the invoice; typed by people with or without separators and with . or , for the
 * Rappen.
 */

/** Highest amount of a QR bill, in Rappen (CHF 9 999 999.99). */
export const MAX_CENTS = 999_999_999

export function formatMoney(cents: number, language: string = 'de'): string {
  const negative = cents < 0
  const value = Math.abs(Math.trunc(cents))
  const francs = Math.floor(value / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, language.startsWith('en') ? ',' : '’')
  const rest = (value % 100).toString().padStart(2, '0')

  return `${negative ? '-' : ''}${francs}.${rest}`
}

/**
 * Rappen from what a person typed: «1500», «1’500.5», «1'500,50», «1 500.00». null for an empty field, NaN for
 * anything else (more than two decimals, letters, a negative amount).
 */
export function parseMoney(text: string): number | null {
  const plain = text.trim().replace(/[\s’'`]/g, '')

  if (plain === '') {
    return null
  }

  const match = /^(\d+)(?:[.,](\d{1,2}))?$/.exec(plain.replace(/,(?=\d{3}(?:\D|$))/g, ''))

  if (!match) {
    return Number.NaN
  }

  return Number(match[1]) * 100 + Number((match[2] ?? '0').padEnd(2, '0'))
}
