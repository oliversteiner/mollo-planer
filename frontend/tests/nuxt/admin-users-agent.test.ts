import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AgentBadge from '~/components/AgentBadge.vue'
import AdminUsersPage from '~/pages/admin/users.vue'
import { installElementInternals } from './support/element-internals'

const { users } = vi.hoisted(() => ({
  users: [
    { id: '2', name: 'Ben Müller', email: 'ben@example.ch', approved: true, createdAt: '2026-11-01T10:00:00Z', avatarUrl: null, isAgent: false, twoFactorConfirmed: true, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } } },
    { id: '3', name: 'Claude', email: 'claude@example.ch', approved: true, createdAt: '2026-11-02T10:00:00Z', avatarUrl: null, isAgent: true, twoFactorConfirmed: false, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } } }
  ]
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ users }), loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() })
}))
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('admin: agent accounts', () => {
  it('marks an agent and says how it gets in, instead of the second factor', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const rows = page.findAll('.users-row')

    expect(rows).toHaveLength(2)
    expect(rows[0]!.findComponent(AgentBadge).exists()).toBe(false)
    expect(rows[1]!.findComponent(AgentBadge).exists()).toBe(true)
    expect(rows[1]!.find('.users-agent-access').text()).toBe('Zugang nur über Token (Befehl app:user-token)')
    expect(rows[1]!.findAll('wa-badge').filter(badge => badge.attributes('variant') === 'warning')).toHaveLength(0)
  })

  it('offers no reset of the second factor for an agent', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const actions = (page.vm as unknown as { actions: (user: unknown) => { label: string }[][] }).actions

    expect(actions(users[0]).flat().map(item => item.label)).toContain('2FA zurücksetzen')
    expect(actions(users[1]).flat().map(item => item.label)).not.toContain('2FA zurücksetzen')
  })
})
