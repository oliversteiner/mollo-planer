import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AgentBadge from '~/components/AgentBadge.vue'
import AppDialog from '~/components/AppDialog.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import AdminUsersPage from '~/pages/admin/users.vue'
import { installElementInternals } from './support/element-internals'

const { users, createAgentToken, revokeAgentToken, ensurePasswordConfirmed } = vi.hoisted(() => ({
  users: [
    { id: '2', name: 'Ben Müller', email: 'ben@example.ch', approved: true, createdAt: '2026-11-01T10:00:00Z', avatarUrl: null, isAgent: false, apiTokens: null, twoFactorConfirmed: true, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } } },
    {
      id: '3', name: 'Claude', email: 'claude@example.ch', approved: true, createdAt: '2026-11-02T10:00:00Z', avatarUrl: null, isAgent: true, twoFactorConfirmed: false, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } },
      apiTokens: [{ id: '11', name: 'Claude Server', createdAt: '2026-11-03T10:00:00Z', lastUsedAt: null, expiresAt: '2027-02-01T10:00:00Z' }]
    }
  ],
  createAgentToken: vi.fn(),
  revokeAgentToken: vi.fn(),
  ensurePasswordConfirmed: vi.fn()
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ users }), loading: ref(false), refetch: vi.fn() }),
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = document?.definitions?.[0]?.name?.value

    return { mutate: name === 'CreateAgentToken' ? createAgentToken : name === 'RevokeAgentToken' ? revokeAgentToken : vi.fn() }
  }
}))
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))
mockNuxtImport('useSecurity', () => () => ({ ensurePasswordConfirmed }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  createAgentToken.mockReset().mockResolvedValue({ data: { createAgentToken: { plainTextToken: '7|geheim', token: { id: '12', name: 'Neu' } } } })
  revokeAgentToken.mockReset().mockResolvedValue({ data: { revokeAgentToken: { id: '11' } } })
  ensurePasswordConfirmed.mockReset().mockResolvedValue(true)
})

describe('admin: agent accounts', () => {
  it('marks an agent and shows its accesses instead of the second factor', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const rows = page.findAll('.users-row')

    expect(rows).toHaveLength(2)
    expect(rows[0]!.findComponent(AgentBadge).exists()).toBe(false)
    expect(rows[1]!.findComponent(AgentBadge).exists()).toBe(true)
    expect(rows[1]!.findAll('wa-badge').filter(badge => badge.attributes('variant') === 'warning')).toHaveLength(0)
  })

  it('has the accesses area for an agent only, with the list and the hint about the command', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const rows = page.findAll('.users-row')

    expect(rows[0]!.find('.users-access').exists()).toBe(false)
    expect(rows[1]!.find('.users-access').exists()).toBe(true)
    expect(rows[1]!.find('.users-access-name').text()).toBe('Claude Server')
    expect(rows[1]!.find('.users-access').text()).toContain('app:user-token')
  })

  it('offers no reset of the second factor for an agent', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const actions = (page.vm as unknown as { actions: (user: unknown) => { label: string }[][] }).actions

    expect(actions(users[0]).flat().map(item => item.label)).toContain('2FA zurücksetzen')
    expect(actions(users[1]).flat().map(item => item.label)).not.toContain('2FA zurücksetzen')
  })

  it('makes an access after the password was confirmed and shows the token once', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const vm = page.vm as unknown as { accessNames: Record<string, string>, createAccess: (user: unknown) => Promise<void>, created: { plainTextToken: string } | null }

    vm.accessNames['3'] = ' Claude Neu '
    await vm.createAccess(users[1])

    expect(ensurePasswordConfirmed).toHaveBeenCalledOnce()
    expect(createAgentToken).toHaveBeenCalledWith({ userId: '3', name: 'Claude Neu' })
    expect(vm.created?.plainTextToken).toBe('7|geheim')
    expect(vm.accessNames['3']).toBe('')

    const dialog = () => page.findAllComponents(AppDialog).find(candidate => candidate.props('label').startsWith('Zugang für'))!

    await vi.waitFor(() => expect(dialog().props('open')).toBe(true))
    expect(dialog().props('label')).toBe('Zugang für Claude erzeugt')
    expect(dialog().text()).toContain('Wird nicht mehr angezeigt.')

    dialog().vm.$emit('update:open', false)
    await vi.waitFor(() => expect(vm.created).toBeNull())
  })

  it('makes nothing when the password prompt is cancelled', async () => {
    ensurePasswordConfirmed.mockResolvedValue(false)
    const page = await mountSuspended(AdminUsersPage)
    const vm = page.vm as unknown as { createAccess: (user: unknown) => Promise<void>, created: unknown }

    await vm.createAccess(users[1])

    expect(createAgentToken).not.toHaveBeenCalled()
    expect(vm.created).toBeNull()
  })

  it('asks with the name before an access is revoked, then revokes it', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const row = page.findAll('.users-row')[1]!

    await row.find('.users-access-row wa-button').trigger('click')
    const modal = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))!

    expect(modal.props('title')).toBe('Zugang «Claude Server» widerrufen?')
    expect(revokeAgentToken).not.toHaveBeenCalled()

    await modal.props('action')()

    expect(revokeAgentToken).toHaveBeenCalledWith({ userId: '3', tokenId: '11' })
  })

  it('keeps the question open and revokes nothing when the password prompt is cancelled', async () => {
    ensurePasswordConfirmed.mockResolvedValue(false)
    const page = await mountSuspended(AdminUsersPage)

    await page.findAll('.users-row')[1]!.find('.users-access-row wa-button').trigger('click')
    const modal = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))!

    await expect(modal.props('action')()).rejects.toThrow()
    expect(revokeAgentToken).not.toHaveBeenCalled()
  })
})
