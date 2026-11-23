import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import AppDialog from '~/components/AppDialog.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import OAuthClientFormModal from '~/components/admin/OAuthClientFormModal.vue'
import OAuthClientsPage from '~/pages/admin/oauth-clients.vue'
import { installElementInternals } from './support/element-internals'

const { clients, createClient, revokeClient, ensurePasswordConfirmed } = vi.hoisted(() => ({
  clients: [
    { id: 'a-1', name: 'Club DB', redirectUris: ['https://clubdb.example/callback'], confidential: true, revoked: false, createdAt: '2026-11-23T10:00:00Z' },
    { id: 'a-2', name: 'Planer iOS', redirectUris: ['ch.mollo.planer:/oauth/callback'], confidential: false, revoked: false, createdAt: '2026-11-23T11:00:00Z' },
    { id: 'a-3', name: 'Alt', redirectUris: ['https://alt.example/cb'], confidential: true, revoked: true, createdAt: '2026-10-01T11:00:00Z' }
  ],
  createClient: vi.fn(),
  revokeClient: vi.fn(),
  ensurePasswordConfirmed: vi.fn()
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ oauthClients: clients }), loading: ref(false), refetch: vi.fn() }),
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = document?.definitions?.[0]?.name?.value

    return { mutate: name === 'CreateOAuthClient' ? createClient : name === 'RevokeOAuthClient' ? revokeClient : vi.fn() }
  }
}))
mockNuxtImport('useSecurity', () => () => ({ ensurePasswordConfirmed }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  createClient.mockReset().mockResolvedValue({ data: { createOAuthClient: { secret: 'geheim-123', client: clients[0] } } })
  revokeClient.mockReset().mockResolvedValue({ data: { revokeOAuthClient: { id: 'a-1', revoked: true } } })
  ensurePasswordConfirmed.mockReset().mockResolvedValue(true)
})

describe('admin: login service clients', () => {
  it('lists the clients with kind, addresses and the revoked mark', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const rows = page.findAll('.clients-row')

    expect(rows).toHaveLength(3)
    expect(rows[0]!.text()).toContain('Club DB')
    expect(rows[0]!.text()).toContain('Vertraulich')
    expect(rows[0]!.text()).toContain('https://clubdb.example/callback')
    expect(rows[1]!.text()).toContain('Öffentlich')
    expect(rows[1]!.text()).toContain('ch.mollo.planer:/oauth/callback')
    expect(rows[2]!.text()).toContain('Widerrufen')
  })

  it('offers revoking for active clients only', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const rows = page.findAll('.clients-row')

    expect(rows[0]!.find('wa-dropdown, [data-action-menu], wa-button').exists()).toBe(true)
    expect(rows[2]!.find('wa-dropdown, [data-action-menu]').exists()).toBe(false)
  })

  it('asks before a client is revoked and revokes after the password was confirmed', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const actions = (page.vm as unknown as { actions: (client: unknown) => { label: string, onSelect: () => void }[][] }).actions

    actions(clients[0]).flat()[0]!.onSelect()
    const modal = await vi.waitFor(() => {
      const open = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))

      expect(open).toBeDefined()

      return open!
    })

    expect(modal.props('title')).toBe('Club DB widerrufen?')
    expect(revokeClient).not.toHaveBeenCalled()

    await modal.props('action')()

    expect(ensurePasswordConfirmed).toHaveBeenCalledOnce()
    expect(revokeClient).toHaveBeenCalledWith({ id: 'a-1' })
  })

  it('revokes nothing when the password prompt is cancelled', async () => {
    ensurePasswordConfirmed.mockResolvedValue(false)
    const page = await mountSuspended(OAuthClientsPage)
    const actions = (page.vm as unknown as { actions: (client: unknown) => { onSelect: () => void }[][] }).actions

    actions(clients[0]).flat()[0]!.onSelect()
    const modal = await vi.waitFor(() => {
      const open = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))

      expect(open).toBeDefined()

      return open!
    })

    await expect(modal.props('action')()).rejects.toThrow()
    expect(revokeClient).not.toHaveBeenCalled()
  })

  it('makes a client after the password was confirmed, one address per line, and shows the secret once', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const form = page.findComponent(OAuthClientFormModal)
    const state = form.vm as unknown as { state: { name: string, redirectUris: string, confidential: boolean }, submit: () => Promise<void> }
    ;(page.vm as unknown as { creating: boolean }).creating = true
    await nextTick()
    state.state.name = ' Club DB '
    state.state.redirectUris = 'https://clubdb.example/callback\n\n  https://clubdb.example/other  \n'
    await state.submit()

    expect(ensurePasswordConfirmed).toHaveBeenCalledOnce()
    expect(createClient).toHaveBeenCalledWith({
      name: 'Club DB',
      redirectUris: ['https://clubdb.example/callback', 'https://clubdb.example/other'],
      confidential: true
    })

    const dialog = () => page.findAllComponents(AppDialog).find(candidate => String(candidate.props('label')).startsWith('Geheimnis für'))!
    await vi.waitFor(() => expect(dialog().props('open')).toBe(true))
    expect(dialog().props('label')).toBe('Geheimnis für Club DB')
    expect(dialog().text()).toContain('Wird nicht mehr angezeigt.')
  })

  it('makes nothing when the password prompt is cancelled', async () => {
    ensurePasswordConfirmed.mockResolvedValue(false)
    const page = await mountSuspended(OAuthClientsPage)
    const form = page.findComponent(OAuthClientFormModal).vm as unknown as { state: { name: string }, submit: () => Promise<void> }

    form.state.name = 'Club DB'
    await form.submit()

    expect(createClient).not.toHaveBeenCalled()
  })
})
