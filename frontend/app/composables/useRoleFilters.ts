/**
 * Filters of the role page (docs/fix-m1-rollenseite.md §3) and its state in
 * the URL, as pure functions: they get the data of one scope and return what
 * is shown. The data is small, so everything is filtered in the browser.
 */

export type RoleScopeKey = 'SYSTEM' | 'PROJECT'
export type RoleView = 'roles' | 'functions' | 'matrix'
export type OriginFilter = 'all' | 'standard' | 'own'
export type UsageFilter = 'all' | 'used' | 'unused'

export interface RoleFilter {
  q: string
  origin: OriginFilter
  rights: string[]
  usage: UsageFilter
}

export interface RolePageState {
  scope: RoleScopeKey
  view: RoleView
  filter: RoleFilter
}

interface Names {
  de: string
  en: string
}

export interface FilterRole {
  id: string
  isStandard: boolean
  usageCount: number
  names: Names
  functions: readonly { id: string, names: Names }[]
}

export interface FilterFunction {
  id: string
  isStandard: boolean
  usageCount: number
  names: Names
  rights: readonly string[]
}

export interface MatrixRow {
  right: string
  roles: readonly { key: string }[]
}

/** The text of a right as the page shows it (its short text). */
export type RightText = (right: string) => string

export const EMPTY_ROLE_FILTER: RoleFilter = { q: '', origin: 'all', rights: [], usage: 'all' }

const VIEWS: readonly RoleView[] = ['roles', 'functions', 'matrix']
const ORIGINS: readonly OriginFilter[] = ['all', 'standard', 'own']
const USAGES: readonly UsageFilter[] = ['all', 'used', 'unused']

/** Lower case and without accents, so "Ubersicht" finds "Übersicht". */
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

export function isRoleFilterActive(filter: RoleFilter): boolean {
  return filter.q.trim() !== '' || filter.origin !== 'all' || filter.rights.length > 0 || filter.usage !== 'all'
}

/** The rights of a role: those of its functions, each once, in the order they come. */
export function rightsOfRole(role: FilterRole, functions: readonly FilterFunction[]): string[] {
  const byId = new Map(functions.map(entry => [entry.id, entry]))

  return [...new Set(role.functions.flatMap(entry => byId.get(entry.id)?.rights ?? []))]
}

function matchesOrigin(entry: { isStandard: boolean }, origin: OriginFilter): boolean {
  return origin === 'all' || (origin === 'standard') === entry.isStandard
}

function matchesUsage(entry: { usageCount: number }, usage: UsageFilter): boolean {
  return usage === 'all' || (usage === 'used') === (entry.usageCount > 0)
}

function hasAll(rights: readonly string[], wanted: readonly string[]): boolean {
  return wanted.every(right => rights.includes(right))
}

export function filterRoles<TRole extends FilterRole>(roles: readonly TRole[], functions: readonly FilterFunction[], filter: RoleFilter, rightText: RightText): TRole[] {
  return roles.filter((role) => {
    const rights = rightsOfRole(role, functions)
    const texts = [role.names.de, role.names.en, ...role.functions.flatMap(entry => [entry.names.de, entry.names.en]), ...rights.map(rightText)]

    return matchesSearch(filter.q, texts)
      && matchesOrigin(role, filter.origin)
      && hasAll(rights, filter.rights)
      && matchesUsage(role, filter.usage)
  })
}

/**
 * The search finds the role only through its rights, not its name or its
 * functions: the row opens, so the hit is visible.
 */
export function roleMatchesOnlyInRights(role: FilterRole, functions: readonly FilterFunction[], query: string, rightText: RightText): boolean {
  if (query.trim() === '') {
    return false
  }

  const names = [role.names.de, role.names.en, ...role.functions.flatMap(entry => [entry.names.de, entry.names.en])]

  return !matchesSearch(query, names) && matchesSearch(query, rightsOfRole(role, functions).map(rightText))
}

/** The same for a function: found only through its rights. */
export function functionMatchesOnlyInRights(entry: FilterFunction, query: string, rightText: RightText): boolean {
  return query.trim() !== '' && !matchesSearch(query, [entry.names.de, entry.names.en]) && matchesSearch(query, entry.rights.map(rightText))
}

export function filterFunctions<TFunction extends FilterFunction>(functions: readonly TFunction[], filter: RoleFilter, rightText: RightText): TFunction[] {
  return functions.filter(entry =>
    matchesSearch(filter.q, [entry.names.de, entry.names.en, ...entry.rights.map(rightText)])
    && matchesOrigin(entry, filter.origin)
    && hasAll(entry.rights, filter.rights)
    && matchesUsage(entry, filter.usage)
  )
}

/**
 * The overview: rows are rights (search and "has right" choose them), columns
 * are roles (origin and usage choose them).
 */
export function filterMatrix<TRole extends { isStandard: boolean, usageCount: number }>(
  rows: readonly MatrixRow[],
  roles: readonly TRole[],
  filter: RoleFilter,
  rightText: RightText
): { rows: MatrixRow[], roles: TRole[] } {
  return {
    rows: rows.filter(row =>
      matchesSearch(filter.q, [rightText(row.right), row.right])
      && (filter.rights.length === 0 || filter.rights.includes(row.right))
    ),
    roles: roles.filter(role => matchesOrigin(role, filter.origin) && matchesUsage(role, filter.usage))
  }
}

/* ---- Groups of rights ---- */

/** Prefix of a right → its group. Longer prefixes first (PROJECTS before PROJECT). */
const GROUP_OF_PREFIX: readonly (readonly [string, string])[] = [
  ['LABEL_SETS', 'TEMPLATES'],
  ['AI_CONTEXT', 'AI_CONTEXT'],
  ['PROJECTS', 'PROJECTS'],
  ['PROJECT', 'PROJECT'],
  ['MEMBERS', 'MEMBERS'],
  ['OWNER', 'MEMBERS'],
  ['MILESTONE', 'MILESTONE'],
  ['TODO', 'TODO'],
  ['TASK', 'TASK'],
  ['COMMENT', 'COMMENT'],
  ['TRASH', 'TRASH'],
  ['PLANER', 'PLANER'],
  ['USERS', 'USERS'],
  ['ROLES', 'ROLES'],
  ['TEMPLATES', 'TEMPLATES'],
  ['ASSISTANT', 'ASSISTANT'],
  ['IDEAS', 'ASSISTANT'],
  ['SETTINGS', 'OPERATIONS'],
  ['SYSTEM', 'OPERATIONS'],
  ['DATABASE', 'OPERATIONS']
]

export function rightGroup(right: string): string {
  return GROUP_OF_PREFIX.find(([prefix]) => right.startsWith(`${prefix}_`))?.[1] ?? 'OTHER'
}

/** Order of the groups on the page (docs/fix-m1-rollenseite.md §2). */
const GROUP_ORDER: readonly string[] = [
  'PROJECT', 'MEMBERS', 'MILESTONE', 'TODO', 'TASK', 'COMMENT', 'TRASH', 'PLANER', 'AI_CONTEXT',
  'PROJECTS', 'USERS', 'ROLES', 'TEMPLATES', 'ASSISTANT', 'OPERATIONS', 'OTHER'
]

/** Rights in groups, the groups in a fixed order; within a group the rights keep their order. */
export function groupRights(rights: readonly string[]): { group: string, rights: string[] }[] {
  const groups = new Map<string, string[]>()

  for (const right of rights) {
    const group = rightGroup(right)
    groups.set(group, [...(groups.get(group) ?? []), right])
  }

  return [...groups]
    .sort(([a], [b]) => GROUP_ORDER.indexOf(a) - GROUP_ORDER.indexOf(b))
    .map(([group, entries]) => ({ group, rights: entries }))
}

/* ---- State in the URL ---- */

type QueryValue = string | null | undefined | readonly (string | null)[]

function first(value: QueryValue): string {
  return (Array.isArray(value) ? value[0] : value) ?? ''
}

function oneOf<T extends string>(value: string, allowed: readonly T[], fallback: T): T {
  return (allowed as readonly string[]).includes(value) ? (value as T) : fallback
}

/** Reads ?scope=system|project&view=…&q=…&origin=…&right=…&usage=…; unknown values fall back to the defaults. */
export function parseRolePageState(query: Record<string, QueryValue>): RolePageState {
  const right = query.right
  const rights = (Array.isArray(right) ? right : right ? [right] : []).filter((entry): entry is string => typeof entry === 'string' && entry !== '')

  return {
    scope: first(query.scope) === 'system' ? 'SYSTEM' : 'PROJECT',
    view: oneOf(first(query.view), VIEWS, 'roles'),
    filter: {
      q: first(query.q),
      origin: oneOf(first(query.origin), ORIGINS, 'all'),
      rights: [...new Set(rights)],
      usage: oneOf(first(query.usage), USAGES, 'all')
    }
  }
}

/** The state as query; defaults are left out, so a plain /admin/roles stays plain. */
export function toRoleQuery(state: RolePageState): Record<string, string | string[]> {
  const query: Record<string, string | string[]> = {}

  if (state.scope === 'SYSTEM') {
    query.scope = 'system'
  }
  if (state.view !== 'roles') {
    query.view = state.view
  }
  if (state.filter.q.trim() !== '') {
    query.q = state.filter.q
  }
  if (state.filter.origin !== 'all') {
    query.origin = state.filter.origin
  }
  if (state.filter.rights.length > 0) {
    query.right = [...state.filter.rights]
  }
  if (state.filter.usage !== 'all') {
    query.usage = state.filter.usage
  }

  return query
}

/* ---- Highlighting ---- */

/**
 * The text in parts, the parts that match a word of the search marked as hit.
 * Compares without case and accents but returns the original text.
 */
export function highlightParts(text: string, query: string): { text: string, hit: boolean }[] {
  const words = normalizeText(query).split(/\s+/).filter(Boolean)

  if (words.length === 0 || text === '') {
    return [{ text, hit: false }]
  }

  // Normalized text and, for each of its characters, the index in the original.
  let normalized = ''
  const origin: number[] = []
  for (let index = 0; index < text.length; index++) {
    for (const char of normalizeText(text.charAt(index))) {
      normalized += char
      origin.push(index)
    }
  }

  const hits = Array.from({ length: text.length }, () => false)
  for (const word of words) {
    let from = normalized.indexOf(word)
    while (from !== -1) {
      for (let offset = 0; offset < word.length; offset++) {
        hits[origin[from + offset] ?? 0] = true
      }
      from = normalized.indexOf(word, from + word.length)
    }
  }

  const parts: { text: string, hit: boolean }[] = []
  for (let index = 0; index < text.length; index++) {
    const last = parts.at(-1)
    const hit = hits[index] ?? false
    if (last && last.hit === hit) {
      last.text += text.charAt(index)
    } else {
      parts.push({ text: text.charAt(index), hit })
    }
  }

  return parts
}
