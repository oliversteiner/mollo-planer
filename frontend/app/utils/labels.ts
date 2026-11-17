import type { LabelColor } from '~/graphql/generated/graphql'

export const LABEL_COLORS: LabelColor[] = ['GRAY', 'RED', 'ORANGE', 'YELLOW', 'GREEN', 'CYAN', 'BLUE', 'INDIGO', 'PURPLE', 'PINK']

/** The languages a label has a name in. */
export const LABEL_LOCALES = ['DE', 'EN'] as const
export type LabelLocale = (typeof LABEL_LOCALES)[number]

/** At most this many labels in a project or a template. */
export const MAX_LABELS = 30

/** A label in the editor: the key of one that exists (it never changes), a name per language. */
export interface LabelRow {
  uid: number
  key?: string
  names: Record<LabelLocale, string>
  category: string
  color: LabelColor
  /** Tasks that carry the label (not for a new one). */
  taskCount?: number
}

/** What the server sends about a label. */
export interface LabelSource {
  key: string
  names: readonly { locale: string, name: string }[]
  category?: string | null
  color: string
  taskCount?: number | null
}

export interface LabelInput {
  key?: string
  names: { locale: LabelLocale, name: string }[]
  category: string | null
  color: LabelColor
}

let nextUid = 0

export function rowFromLabel(label: LabelSource): LabelRow {
  const names: Record<LabelLocale, string> = { DE: '', EN: '' }

  for (const { locale, name } of label.names) {
    if (locale === 'DE' || locale === 'EN') {
      names[locale] = name
    }
  }

  return { uid: nextUid++, key: label.key, names, category: label.category ?? '', color: label.color as LabelColor, taskCount: label.taskCount ?? undefined }
}

/** A new label gets the first colour no label has yet (grey last). */
export function freeColor(rows: readonly LabelRow[]): LabelColor {
  const used = new Set(rows.map(row => row.color))
  const order = [...LABEL_COLORS.slice(1), LABEL_COLORS[0]!]

  return order.find(color => !used.has(color)) ?? order[rows.length % order.length]!
}

/** An empty row for a new label, in the category of the one above. */
export function newRow(rows: readonly LabelRow[]): LabelRow {
  return { uid: nextUid++, names: { DE: '', EN: '' }, category: rows.at(-1)?.category ?? '', color: freeColor(rows) }
}

/** The input of the mutations: a key for a label that exists, only the names that are filled in. */
export function labelInputs(rows: readonly LabelRow[]): LabelInput[] {
  return rows.map(row => ({
    ...(row.key ? { key: row.key } : {}),
    names: LABEL_LOCALES.map(locale => ({ locale, name: row.names[locale].trim() })).filter(entry => entry.name !== ''),
    category: row.category.trim() || null,
    color: row.color
  }))
}

/** The name in the language the project or template needs is missing. */
export function lacksName(row: LabelRow, required: LabelLocale): boolean {
  return row.names[required].trim() === ''
}

/** The labels that exist and are no longer in the list, and the tasks that lose them. */
export function removedLabels(original: readonly { key: string, taskCount?: number | null }[], rows: readonly LabelRow[]): { count: number, tasks: number } {
  const kept = new Set(rows.map(row => row.key).filter(Boolean))
  const gone = original.filter(label => !kept.has(label.key))

  return { count: gone.length, tasks: gone.reduce((sum, label) => sum + (label.taskCount ?? 0), 0) }
}

/**
 * What taking over the labels of a template does in a project (docs/auftrag-m3.md 3.3):
 * REPLACE updates labels with the same key, adds the others, keeps labels that tasks carry
 * and that the template lacks (at the end) and deletes the rest; APPEND adds what is missing.
 */
export function templateLabelsPlan(
  project: readonly { key: string, taskCount?: number | null }[],
  template: readonly { key: string }[],
  mode: 'REPLACE' | 'APPEND'
): { added: number, updated: number, kept: number, removed: number, total: number } {
  const have = new Set(project.map(label => label.key))
  const wanted = new Set(template.map(label => label.key))
  const added = template.filter(label => !have.has(label.key)).length

  if (mode === 'APPEND') {
    return { added, updated: 0, kept: project.length, removed: 0, total: project.length + added }
  }

  const missing = project.filter(label => !wanted.has(label.key))
  const kept = missing.filter(label => (label.taskCount ?? 0) > 0).length

  return { added, updated: template.length - added, kept, removed: missing.length - kept, total: template.length + kept }
}

/** The name of a label of a template in the language of the project, else in the language of the template. */
export function nameFallbacks(labels: readonly { names: readonly { locale: string }[] }[], projectLocale: string): number {
  return labels.filter(label => !label.names.some(entry => entry.locale === projectLocale)).length
}
