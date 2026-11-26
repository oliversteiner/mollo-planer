import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import AppDialog from '~/components/AppDialog.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import PasswordConfirmField from '~/components/PasswordConfirmField.vue'
import OAuthClientFormModal from '~/components/admin/OAuthClientFormModal.vue'
import OAuthClientsPage from '~/pages/admin/oauth-clients.vue'
import { stubBackend } from './support/backend'
import { installElementInternals } from './support/element-internals'

const { clients, createClient, revokeClient, backend } = vi.hoisted(() => ({
  clients: [
    { id: 'a-1', name: 'Club DB', redirectUris: ['https://clubdb.example/callback'], confidential: true, revoked: false, selfRegistered: false, createdAt: '2026-11-23T10:00:00Z' },
    { id: 'a-2', name: 'Planer iOS', redirectUris: ['ch.mollo.planer:/oauth/callback'], confidential: false, revoked: false, selfRegistered: true, createdAt: '2026-11-23T11:00:00Z' },
    { id: 'a-3', name: 'Alt', redirectUris: ['https://alt.example/cb'], confidential: true, revoked: true, selfRegistered: false, createdAt: '2026-10-01T11:00:00Z' }
  ],
  createClient: vi.fn(),
  revokeClient: vi.fn(),
  backend: vi.fn()
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ oauthClients: clients }), loading: ref(false), refetch: vi.fn() }),
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = document?.definitions?.[0]?.name?.value

    return { mutate: name === 'CreateOAuthClient' ? createClient : name === 'RevokeOAuthClient' ? revokeClient : vi.fn() }
  }
}))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  createClient.mockReset().mockResolvedValue({ data: { createOAuthClient: { secret: 'geheim-123', client: clients[0] } } })
  revokeClient.mockReset().mockResolvedValue({ data: { revokeOAuthClient: { id: 'a-1', revoked: true } } })
  // The password confirmation of the dialogs (usePasswordStep) asks the backend: confirmed a moment ago, unless a test says not.
  backend.mockReset().mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: true } : {}))
  stubBackend(backend)
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

  it('marks a client that registered itself', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const rows = page.findAll('.clients-row')

    expect(rows[1]!.text()).toContain('Selbst registriert')
    expect(rows[0]!.text()).not.toContain('Selbst registriert')
  })

  it('offers revoking for active clients only', async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const rows = page.findAll('.clients-row')

    expect(rows[0]!.find('wa-dropdown, [data-action-menu], wa-button').exists()).toBe(true)
    expect(rows[2]!.find('wa-dropdown, [data-action-menu]').exists()).toBe(false)
  })

  const openQuestion = async () => {
    const page = await mountSuspended(OAuthClientsPage)
    const actions = (page.vm as unknown as { actions: (client: unknown) => { label: string, onSelect: () => void }[][] }).actions

    actions(clients[0]).flat()[0]!.onSelect()
    const modal = await vi.waitFor(() => {
      const open = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))

      expect(open).toBeDefined()

      return open!
    })

    return { page, modal }
  }

  it('asks before a client is revoked, the password in the same dialog (never in a second one), then revokes', async () => {
    const { modal } = await openQuestion()

    expect(modal.props('title')).toBe('Club DB widerrufen?')
    expect(modal.props('requiresPassword')).toBe(true)
    expect(revokeClient).not.toHaveBeenCalled()

    await modal.findAll('wa-button').find(button => button.text() === 'Ja, widerrufen')!.trigger('click')

    await vi.waitFor(() => expect(revokeClient).toHaveBeenCalledWith({ id: 'a-1' }))
  })

  it('shows the password field in the question when the confirmation is old, and revokes nothing before it', async () => {
    backend.mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: false } : {}))
    const { modal } = await openQuestion()

    await modal.findAll('wa-button').find(button => button.text() === 'Ja, widerrufen')!.trigger('click')
    await vi.waitFor(() => expect(modal.find('.password-confirm').exists()).toBe(true))

    expect(revokeClient).not.toHaveBeenCalled()

    modal.findComponent(PasswordConfirmField).vm.$props.step.cancel()
    await nextTick()

    expect(modal.find('.password-confirm').exists()).toBe(false)
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

  it('asks for the password in a field of the form and makes nothing when it is cancelled', async () => {
    backend.mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: false } : {}))
    const page = await mountSuspended(OAuthClientsPage)
    const formComponent = page.findComponent(OAuthClientFormModal)
    const form = formComponent.vm as unknown as { state: { name: string }, submit: () => Promise<void> }
    ;(page.vm as unknown as { creating: boolean }).creating = true
    await nextTick()

    form.state.name = 'Club DB'
    await form.submit()
    await vi.waitFor(() => expect(formComponent.find('.password-confirm').exists()).toBe(true))

    expect(createClient).not.toHaveBeenCalled()

    formComponent.findComponent(PasswordConfirmField).vm.$props.step.cancel()
    await nextTick()

    expect(createClient).not.toHaveBeenCalled()
  })
})
