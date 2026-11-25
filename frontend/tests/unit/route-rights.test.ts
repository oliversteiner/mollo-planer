import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { ROUTE_RIGHTS, routeRight } from '../../app/utils/route-rights'

const rightsEnum = readFileSync(fileURLToPath(new URL('../../../backend/app/Enums/Right.php', import.meta.url)), 'utf8')
const knownRights = new Set([...rightsEnum.matchAll(/case (\w+) = '/g)].map(match => match[1]))

describe('route rights (RF5)', () => {
  it('maps the admin pages to their rights', () => {
    expect(routeRight('/admin/roles')).toBe('ROLES_MANAGE')
    expect(routeRight('/admin/oauth-clients')).toBe('SETTINGS_MANAGE')
    expect(routeRight('/billing/invoices/12')).toBe('BILLING_MANAGE')
    expect(routeRight('/billing')).toBe('BILLING_MANAGE')
    expect(routeRight('/admin/settings')).toBe('SETTINGS_MANAGE')
    expect(routeRight('/admin/users')).toBe('USERS_MANAGE')
    expect(routeRight('/system/db')).toBe('USERS_MANAGE')
    expect(routeRight('/assistant')).toBe('ASSISTANT_USE')
    expect(routeRight('/ideas')).toBe('IDEAS_CREATE')
  })

  it('takes sub-paths and the longest prefix', () => {
    expect(routeRight('/system/db/users')).toBe('USERS_MANAGE')
    expect(routeRight('/admin/roles/42')).toBe('ROLES_MANAGE')
  })

  it('needs no right for other paths, also not for a path that only starts the same way', () => {
    expect(routeRight('/')).toBeNull()
    expect(routeRight('/my-tasks')).toBeNull()
    expect(routeRight('/projects/12')).toBeNull()
    expect(routeRight('/system/status')).toBeNull()
    expect(routeRight('/system/dbx')).toBeNull()
  })

  it('names only rights of the backend enum Right', () => {
    for (const right of Object.values(ROUTE_RIGHTS)) {
      expect(knownRights.has(right), right).toBe(true)
    }
  })
})
