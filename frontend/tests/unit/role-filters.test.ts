import { describe, expect, it } from 'vitest'
import {
  EMPTY_ROLE_FILTER,
  filterFunctions,
  filterMatrix,
  filterRoles,
  functionMatchesOnlyInRights,
  groupRights,
  highlightParts,
  isRoleFilterActive,
  matchesSearch,
  parseRolePageState,
  rightGroup,
  rightsOfRole,
  roleMatchesOnlyInRights,
  toRoleQuery,
  type FilterFunction,
  type FilterRole,
  type RoleFilter
} from '../../app/composables/useRoleFilters'

const TEXTS: Record<string, string> = {
  TASK_CREATE: 'Task anlegen',
  TASK_UPDATE: 'Task ändern',
  PROJECT_DELETE: 'Projekt löschen',
  MILESTONE_CREATE: 'Milestone anlegen',
  TRASH_PURGE: 'Papierkorb leeren'
}
const rightText = (right: string): string => TEXTS[right] ?? right

const plan: FilterFunction = { id: 'f1', isStandard: true, usageCount: 3, names: { de: 'Planen', en: 'Plan' }, rights: ['MILESTONE_CREATE'] }
const contribute: FilterFunction = { id: 'f2', isStandard: true, usageCount: 2, names: { de: 'Mitarbeiten', en: 'Contribute' }, rights: ['TASK_CREATE', 'TASK_UPDATE'] }
const own: FilterFunction = { id: 'f3', isStandard: false, usageCount: 0, names: { de: 'Aufräumen', en: 'Clean up' }, rights: ['TRASH_PURGE', 'PROJECT_DELETE'] }
const functions = [plan, contribute, own]

const owner: FilterRole = { id: 'r1', isStandard: true, usageCount: 3, names: { de: 'Owner', en: 'Owner' }, functions: [plan, contribute, own] }
const member: FilterRole = { id: 'r2', isStandard: true, usageCount: 5, names: { de: 'Mitglied', en: 'Member' }, functions: [plan, contribute] }
const editor: FilterRole = { id: 'r3', isStandard: false, usageCount: 0, names: { de: 'Redaktion', en: 'Editorial' }, functions: [plan] }
const roles = [owner, member, editor]

const filter = (change: Partial<RoleFilter>): RoleFilter => ({ ...EMPTY_ROLE_FILTER, ...change })
const ids = (entries: readonly { id: string }[]): string[] => entries.map(entry => entry.id)

describe('search', () => {
  it('ignores case and accents and needs every word', () => {
    expect(matchesSearch('AUFRAUMEN', ['Aufräumen'])).toBe(true)
    expect(matchesSearch('task ändern', ['Task ändern'])).toBe(true)
    expect(matchesSearch('task leeren', ['Task ändern'])).toBe(false)
    expect(matchesSearch('  ', ['egal'])).toBe(true)
  })
})

describe('roles', () => {
  it('collects the rights of their functions once each', () => {
    expect(rightsOfRole(owner, functions)).toEqual(['MILESTONE_CREATE', 'TASK_CREATE', 'TASK_UPDATE', 'TRASH_PURGE', 'PROJECT_DELETE'])
  })

  it('search finds names in both languages, function names and right texts', () => {
    expect(ids(filterRoles(roles, functions, filter({ q: 'editorial' }), rightText))).toEqual(['r3'])
    expect(ids(filterRoles(roles, functions, filter({ q: 'mitarbeiten' }), rightText))).toEqual(['r1', 'r2'])
    expect(ids(filterRoles(roles, functions, filter({ q: 'papierkorb' }), rightText))).toEqual(['r1'])
  })

  it('origin, has right and usage', () => {
    expect(ids(filterRoles(roles, functions, filter({ origin: 'own' }), rightText))).toEqual(['r3'])
    expect(ids(filterRoles(roles, functions, filter({ origin: 'standard' }), rightText))).toEqual(['r1', 'r2'])
    expect(ids(filterRoles(roles, functions, filter({ rights: ['TASK_CREATE', 'PROJECT_DELETE'] }), rightText))).toEqual(['r1'])
    expect(ids(filterRoles(roles, functions, filter({ usage: 'unused' }), rightText))).toEqual(['r3'])
    expect(ids(filterRoles(roles, functions, filter({ usage: 'used' }), rightText))).toEqual(['r1', 'r2'])
  })

  it('combines the filters', () => {
    expect(ids(filterRoles(roles, functions, filter({ origin: 'standard', rights: ['TASK_UPDATE'], usage: 'used', q: 'member' }), rightText))).toEqual(['r2'])
    expect(filterRoles(roles, functions, filter({ origin: 'own', rights: ['TASK_UPDATE'] }), rightText)).toEqual([])
  })

  it('opens a row when the search finds it only in its rights', () => {
    expect(roleMatchesOnlyInRights(owner, functions, 'papierkorb', rightText)).toBe(true)
    expect(roleMatchesOnlyInRights(owner, functions, 'owner', rightText)).toBe(false)
    expect(roleMatchesOnlyInRights(owner, functions, '', rightText)).toBe(false)
  })
})

describe('functions', () => {
  it('search, origin, has right and usage', () => {
    expect(ids(filterFunctions(functions, filter({ q: 'leeren' }), rightText))).toEqual(['f3'])
    expect(ids(filterFunctions(functions, filter({ q: 'clean' }), rightText))).toEqual(['f3'])
    expect(ids(filterFunctions(functions, filter({ origin: 'own' }), rightText))).toEqual(['f3'])
    expect(ids(filterFunctions(functions, filter({ rights: ['TASK_CREATE'] }), rightText))).toEqual(['f2'])
    expect(ids(filterFunctions(functions, filter({ usage: 'unused' }), rightText))).toEqual(['f3'])
  })

  it('opens a row when the search finds it only in its rights', () => {
    expect(functionMatchesOnlyInRights(contribute, 'andern', rightText)).toBe(true)
    expect(functionMatchesOnlyInRights(contribute, 'mitarbeiten', rightText)).toBe(false)
  })
})

describe('overview', () => {
  const rows = [
    { right: 'TASK_CREATE', roles: [{ key: 'owner' }] },
    { right: 'PROJECT_DELETE', roles: [{ key: 'owner' }] }
  ]

  it('chooses rows by search (text or key) and has right, columns by origin and usage', () => {
    expect(filterMatrix(rows, roles, filter({ q: 'loschen' }), rightText).rows.map(row => row.right)).toEqual(['PROJECT_DELETE'])
    expect(filterMatrix(rows, roles, filter({ q: 'task_create' }), rightText).rows.map(row => row.right)).toEqual(['TASK_CREATE'])
    expect(filterMatrix(rows, roles, filter({ rights: ['TASK_CREATE'] }), rightText).rows).toHaveLength(1)
    expect(ids(filterMatrix(rows, roles, filter({ origin: 'own' }), rightText).roles)).toEqual(['r3'])
    expect(ids(filterMatrix(rows, roles, filter({ usage: 'used' }), rightText).roles)).toEqual(['r1', 'r2'])
  })
})

describe('groups of rights', () => {
  it('puts each right into its area', () => {
    expect(rightGroup('PROJECTS_CREATE')).toBe('PROJECTS')
    expect(rightGroup('PROJECT_DELETE')).toBe('PROJECT')
    expect(rightGroup('OWNER_MANAGE')).toBe('MEMBERS')
    expect(rightGroup('LABEL_SETS_MANAGE_ANY')).toBe('TEMPLATES')
    expect(rightGroup('AI_CONTEXT_UPDATE')).toBe('AI_CONTEXT')
    expect(rightGroup('SOMETHING_NEW')).toBe('OTHER')
  })

  it('orders the groups by area and keeps the order of the rights within a group', () => {
    expect(groupRights(['TASK_CREATE', 'PROJECT_DELETE', 'TASK_UPDATE', 'MILESTONE_CREATE'])).toEqual([
      { group: 'PROJECT', rights: ['PROJECT_DELETE'] },
      { group: 'MILESTONE', rights: ['MILESTONE_CREATE'] },
      { group: 'TASK', rights: ['TASK_CREATE', 'TASK_UPDATE'] }
    ])
  })
})

describe('state in the URL', () => {
  it('reads every part and falls back to the defaults', () => {
    expect(parseRolePageState({})).toEqual({ scope: 'PROJECT', view: 'roles', filter: EMPTY_ROLE_FILTER })
    expect(parseRolePageState({ scope: 'system', view: 'matrix', q: 'task', origin: 'own', right: ['TASK_CREATE', 'TASK_CREATE'], usage: 'unused' })).toEqual({
      scope: 'SYSTEM',
      view: 'matrix',
      filter: { q: 'task', origin: 'own', rights: ['TASK_CREATE'], usage: 'unused' }
    })
    expect(parseRolePageState({ view: 'anything', origin: 'x', usage: 'y' })).toEqual({ scope: 'PROJECT', view: 'roles', filter: EMPTY_ROLE_FILTER })
    expect(parseRolePageState({ right: 'PROJECT_DELETE' }).filter.rights).toEqual(['PROJECT_DELETE'])
  })

  it('writes only what differs from the defaults, and reads it back the same', () => {
    expect(toRoleQuery({ scope: 'PROJECT', view: 'roles', filter: EMPTY_ROLE_FILTER })).toEqual({})

    const state = { scope: 'SYSTEM' as const, view: 'functions' as const, filter: { q: 'rolle', origin: 'standard' as const, rights: ['ROLES_MANAGE'], usage: 'used' as const } }
    expect(toRoleQuery(state)).toEqual({ scope: 'system', view: 'functions', q: 'rolle', origin: 'standard', right: ['ROLES_MANAGE'], usage: 'used' })
    expect(parseRolePageState(toRoleQuery(state))).toEqual(state)
  })

  it('knows when a filter is set', () => {
    expect(isRoleFilterActive(EMPTY_ROLE_FILTER)).toBe(false)
    expect(isRoleFilterActive(filter({ usage: 'used' }))).toBe(true)
    expect(isRoleFilterActive(filter({ q: ' ' }))).toBe(false)
  })
})

describe('highlighting', () => {
  it('marks the matching parts without case and accents, in the original spelling', () => {
    expect(highlightParts('Projekt löschen', 'losch')).toEqual([
      { text: 'Projekt ', hit: false },
      { text: 'lösch', hit: true },
      { text: 'en', hit: false }
    ])
    expect(highlightParts('Task anlegen', 'TASK legen')).toEqual([
      { text: 'Task', hit: true },
      { text: ' an', hit: false },
      { text: 'legen', hit: true }
    ])
    expect(highlightParts('Owner', '')).toEqual([{ text: 'Owner', hit: false }])
  })
})
