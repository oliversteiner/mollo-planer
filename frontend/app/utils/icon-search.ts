/** One row of /icons/catalog.json: name, English label, search terms. */
export type IconEntry = [name: string, label: string, terms: string]

/** Shown before anything is typed: icons that fit projects. */
export const SUGGESTED_ICONS = [
  'rocket', 'flag-checkered', 'list-check', 'calendar-days', 'users', 'file-lines', 'palette', 'browser',
  'server', 'coins', 'calculator', 'book', 'lightbulb', 'bug', 'wrench', 'chart-line', 'envelope', 'phone',
  'cart-shopping', 'truck', 'camera', 'trophy', 'star', 'heart', 'house', 'globe', 'lock', 'futbol',
  'bullhorn', 'handshake', 'clipboard-list', 'magnifying-glass', 'pen-ruler', 'gears', 'shield-check'
]

/**
 * Icons whose name, label or terms contain every word of the query; exact
 * and prefix matches of the name first.
 */
export function searchIcons(icons: IconEntry[], query: string, limit = 120): IconEntry[] {
  const words = query.toLowerCase().split(/\s+/).filter(Boolean)

  if (!words.length) {
    const byName = new Map(icons.map(icon => [icon[0], icon]))

    return SUGGESTED_ICONS
      .map(name => byName.get(name))
      .filter((icon): icon is IconEntry => icon !== undefined)
  }

  const rank = (icon: IconEntry): number => {
    const [name] = icon
    const first = words[0] ?? ''

    if (name === first) {
      return 0
    }

    return name.startsWith(first) ? 1 : 2
  }

  return icons
    .filter(([name, label, terms]) => {
      const haystack = `${name} ${label} ${terms}`.toLowerCase()

      return words.every(word => haystack.includes(word))
    })
    .sort((a, b) => rank(a) - rank(b) || a[0].localeCompare(b[0]))
    .slice(0, limit)
}
