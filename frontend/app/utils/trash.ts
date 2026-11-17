/** Order of the children in „mit 2 Milestones, 3 ToDos, 12 Tasks“: from the top down. */
const ORDER = ['PROJECT', 'MILESTONE', 'TODO', 'TASK', 'COMMENT'] as const

export type TrashType = typeof ORDER[number]

/**
 * What went with a deletion, as one list: „2 ToDos, 12 Tasks“. `name`
 * gives the words for one type and count (the i18n plural). Empty when
 * nothing went with it.
 */
export function trashSummary(counts: readonly { type: TrashType, count: number }[], name: (type: TrashType, count: number) => string): string {
  return [...counts]
    .filter(entry => entry.count > 0)
    .sort((a, b) => ORDER.indexOf(a.type) - ORDER.indexOf(b.type))
    .map(entry => name(entry.type, entry.count))
    .join(', ')
}
