/**
 * Search in the interface: without case and accents, every word must appear.
 * Used by the role page filters and the person selection.
 */

/** Lower case and without accents, so "Muller" finds "Müller". */
export function normalizeText(text: string): string {
  return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

/** Every word of the search appears somewhere in the texts. An empty search matches. */
export function matchesSearch(query: string, texts: readonly string[]): boolean {
  const words = normalizeText(query).split(/\s+/).filter(Boolean)

  if (words.length === 0) {
    return true
  }

  const haystack = normalizeText(texts.join(' '))

  return words.every(word => haystack.includes(word))
}
