import { describe, expect, it } from 'vitest'
import { assignableRoles, creatorRoles, isOwnershipRole } from '../../app/utils/role-rights'

const owner = { key: 'owner', rights: ['PROJECT_DELETE', 'OWNER_MANAGE', 'TASK_CREATE'] }
const keeper = { key: 'keeper', rights: ['PROJECT_DELETE'] }
const manager = { key: 'manager', rights: ['MEMBERS_MANAGE', 'TASK_ASSIGN'] }
const guest = { key: 'guest', rights: [] }
const roles = [owner, keeper, manager, guest]
const keys = (entries: readonly { key: string }[]): string[] => entries.map(entry => entry.key)

describe('roles by their rights', () => {
  it('knows the ownership roles', () => {
    expect(isOwnershipRole(owner)).toBe(true)
    expect(isOwnershipRole(keeper)).toBe(true)
    expect(isOwnershipRole(manager)).toBe(false)
  })

  it('offers as creator role only roles that may delete the project', () => {
    expect(keys(creatorRoles(roles))).toEqual(['owner', 'keeper'])
  })

  it('offers ownership roles only to people with owner.manage, and keeps the current role', () => {
    expect(keys(assignableRoles(roles, ['OWNER_MANAGE', 'MEMBERS_MANAGE']))).toEqual(['owner', 'keeper', 'manager', 'guest'])
    expect(keys(assignableRoles(roles, ['MEMBERS_MANAGE']))).toEqual(['manager', 'guest'])
    expect(keys(assignableRoles(roles, ['MEMBERS_MANAGE'], 'owner'))).toEqual(['owner', 'manager', 'guest'])
  })
})
