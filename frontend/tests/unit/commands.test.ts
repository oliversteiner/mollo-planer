import { describe, expect, it } from 'vitest'
import { filterCommands, pageCommands, visibleCommands, type Command } from '../../app/utils/commands'

const t = (key: string) => key
const pages = pageCommands(t, () => undefined)

/** The system rights of the roles that matter here (backend config/permissions.php). */
const roles = {
  lesend: ['SYSTEM_VIEW'],
  mitglied: ['ASSISTANT_USE', 'IDEAS_CREATE', 'SYSTEM_VIEW'],
  projektleitung: ['PROJECTS_CREATE', 'TEMPLATES_MANAGE', 'ASSISTANT_USE', 'IDEAS_CREATE', 'SYSTEM_VIEW'],
  admin: ['USERS_MANAGE', 'ROLES_MANAGE', 'SETTINGS_MANAGE', 'ASSISTANT_USE', 'IDEAS_CREATE', 'SYSTEM_VIEW', 'DATABASE_VIEW']
}

function idsFor(rights: string[]): string[] {
  return visibleCommands(pages, right => rights.includes(right)).map(command => command.id)
}

describe('command palette entries', () => {
  it('shows pages without a right to everybody, also a read-only account', () => {
    expect(idsFor(roles.lesend)).toEqual(expect.arrayContaining(['page-dashboard', 'page-my-tasks', 'page-profile']))
  })

  it('hides the assistant and ideas without their rights, the admin pages without USERS_MANAGE', () => {
    const lesend = idsFor(roles.lesend)

    expect(lesend).not.toContain('page-assistant')
    expect(lesend).not.toContain('page-ideas')
    expect(idsFor(roles.mitglied)).toEqual(expect.arrayContaining(['page-assistant', 'page-ideas']))
    expect(idsFor(roles.mitglied)).not.toContain('page-users')
    expect(idsFor(roles.projektleitung)).not.toContain('page-roles')
    expect(idsFor(roles.admin)).toEqual(expect.arrayContaining(['page-users', 'page-roles', 'page-settings', 'page-database']))
  })

  it('hides an entry whose page says no (a project right) and finds by words without accents', () => {
    const entries: Command[] = [
      { id: 'a', label: 'Milestone anlegen', group: 'action', visible: () => false, run: () => undefined },
      { id: 'b', label: 'Übersicht öffnen', group: 'action', keywords: ['start'], run: () => undefined }
    ]

    expect(visibleCommands(entries, () => true).map(entry => entry.id)).toEqual(['b'])
    expect(filterCommands(entries, 'ubersicht').map(entry => entry.id)).toEqual(['b'])
    expect(filterCommands(entries, 'start').map(entry => entry.id)).toEqual(['b'])
    expect(filterCommands(entries, 'zzz')).toEqual([])
  })
})
