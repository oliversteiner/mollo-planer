import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AgentAccessDialog from '~/components/admin/AgentAccessDialog.vue'
import AgentFormModal from '~/components/admin/AgentFormModal.vue'
import AgentBadge from '~/components/AgentBadge.vue'
import AppDialog from '~/components/AppDialog.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import PasswordConfirmField from '~/components/PasswordConfirmField.vue'
import AdminUsersPage from '~/pages/admin/users.vue'
import { stubBackend } from './support/backend'
import { installElementInternals } from './support/element-internals'

const { users, projects, roles, createAgent, createAgentToken, revokeAgentToken, setMember, removeMember, backend } = vi.hoisted(() => ({
  users: [
    { id: '2', name: 'Ben Müller', email: 'ben@example.ch', approved: true, createdAt: '2026-11-01T10:00:00Z', avatarUrl: null, isAgent: false, apiTokens: null, twoFactorConfirmed: true, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } } },
    {
      id: '3', name: 'Claude', email: 'claude@example.ch', approved: true, createdAt: '2026-11-02T10:00:00Z', avatarUrl: null, isAgent: true, twoFactorConfirmed: false, hasPasskey: false, role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } },
      apiTokens: [{ id: '11', name: 'Claude Server', createdAt: '2026-11-03T10:00:00Z', lastUsedAt: null, expiresAt: '2099-02-01T10:00:00Z' }]
    },
    { id: '4', name: 'Neu Agent', email: 'neu@example.ch', approved: true, createdAt: '2026-11-04T10:00:00Z', avatarUrl: null, isAgent: true, twoFactorConfirmed: false, hasPasskey: false, apiTokens: [], role: { id: '3', key: 'mitglied', isDefault: true, names: { de: 'Mitglied', en: 'Member' } } }
  ],
  // Alpha: the agent is a member and the person manages it; Beta: not a member, managed; Gamma: not a member, not managed.
  projects: [
    { id: '1', name: 'Alpha', viewerRights: ['MEMBERS_MANAGE'], members: [{ role: { key: 'agent', names: { de: 'Agent', en: 'Agent' } }, user: { id: '3' } }] },
    { id: '2', name: 'Beta', viewerRights: ['MEMBERS_MANAGE'], members: [] },
    { id: '3', name: 'Gamma', viewerRights: [], members: [] }
  ],
  roles: [
    { id: '1', key: 'member', scope: 'PROJECT', isDefault: true, names: { de: 'Mitglied', en: 'Member' }, rights: [] },
    { id: '2', key: 'agent', scope: 'PROJECT', isDefault: false, names: { de: 'Agent', en: 'Agent' }, rights: [] }
  ],
  createAgent: vi.fn(),
  createAgentToken: vi.fn(),
  revokeAgentToken: vi.fn(),
  setMember: vi.fn(),
  removeMember: vi.fn(),
  backend: vi.fn()
}))

function operation(document: { definitions?: { name?: { value: string } }[] }): string | undefined {
  return document?.definitions?.[0]?.name?.value
}

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = operation(document)
    const result = name === 'AgentProjects' ? { projects } : name === 'Roles' ? { roles } : { users }

    return { result: ref(result), loading: ref(false), refetch: vi.fn() }
  },
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = operation(document)
    const mutate = { CreateAgent: createAgent, CreateAgentToken: createAgentToken, RevokeAgentToken: revokeAgentToken, SetProjectMember: setMember, RemoveProjectMember: removeMember }[name ?? ''] ?? vi.fn()

    return { mutate }
  }
}))
mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '1' }), load: vi.fn(), logout: vi.fn(), can: () => true, pendingEmail: ref(null) }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  createAgent.mockReset().mockResolvedValue({ data: { createAgent: { id: '9', name: 'Claude Neu' } } })
  createAgentToken.mockReset().mockResolvedValue({ data: { createAgentToken: { plainTextToken: '7|geheim', token: { id: '12', name: 'Neu' } } } })
  revokeAgentToken.mockReset().mockResolvedValue({ data: { revokeAgentToken: { id: '11' } } })
  setMember.mockReset().mockResolvedValue({ data: {} })
  removeMember.mockReset().mockResolvedValue({ data: {} })
  // The password confirmation of the dialogs (usePasswordStep) asks the backend: confirmed a moment ago, unless a test says not.
  backend.mockReset().mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: true } : {}))
  stubBackend(backend)
})

describe('admin users: sections and access indicator', () => {
  it('lists people and agents in two sections with their count', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const sections = page.findAll('.users-section')

    expect(sections).toHaveLength(2)
    expect(sections[0]!.find('h2').text()).toBe('Benutzer (1)')
    expect(sections[1]!.find('h2').text()).toBe('Agenten (2)')
    expect(sections[0]!.findComponent(AgentBadge).exists()).toBe(false)
    expect(sections[1]!.findAllComponents(AgentBadge)).toHaveLength(2)
    expect(sections[0]!.text()).not.toContain('Claude')
  })

  it('shows one line per agent with the state of the access and no access block', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const indicators = page.findAll('.users-access')

    expect(indicators.map(indicator => indicator.attributes('data-state'))).toEqual(['active', 'none'])
    expect(indicators.map(indicator => indicator.text())).toEqual(['Zugang aktiv', 'Zugang einrichten'])
    expect(page.find('.users-access-list').exists()).toBe(false)
    expect(page.text()).not.toContain('app:user-token')
  })

  it('filters both sections with the search and says so when nothing matches', async () => {
    const page = await mountSuspended(AdminUsersPage)

    ;(page.vm as unknown as { query: string }).query = 'ben'
    await vi.waitFor(() => expect(page.findAll('.users-section')[1]!.text()).toContain('Keine Treffer.'))

    expect(page.findAll('.users-section')[0]!.findAll('.users-row')).toHaveLength(1)
  })

  it('offers the dialog in the menu of an agent only, and no reset of the second factor for it', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const actions = (page.vm as unknown as { actions: (user: unknown) => { label: string }[][] }).actions

    expect(actions(users[0]).flat().map(item => item.label)).toContain('2FA zurücksetzen')
    expect(actions(users[0]).flat().map(item => item.label)).not.toContain('Zugang und Projekte')
    expect(actions(users[1]).flat().map(item => item.label)).toContain('Zugang und Projekte')
    expect(actions(users[1]).flat().map(item => item.label)).not.toContain('2FA zurücksetzen')
  })

  it('opens the dialog of the agent from its indicator', async () => {
    const page = await mountSuspended(AdminUsersPage)

    expect(page.findComponent(AgentAccessDialog).exists()).toBe(false)

    await page.findAll('.users-access')[0]!.trigger('click')

    expect(page.findComponent(AgentAccessDialog).props('agent')).toMatchObject({ id: '3' })
  })
})

describe('admin users: dialog of an agent', () => {
  async function open(agent: (typeof users)[number] = users[1]!) {
    const dialog = await mountSuspended(AgentAccessDialog, { props: { agent: agent as never } })

    return { dialog, vm: dialog.vm as unknown as { accessName: string, created: string | null, createAccess: () => Promise<void>, newProjectId: string | null, newRole: string, assign: () => Promise<void> } }
  }
  it('shows the explanation, the steps with their state and the address of the server', async () => {
    const { dialog } = await open()

    expect(dialog.findComponent(AppDialog).props('label')).toBe('Zugang für Claude')
    expect(dialog.find('.access-intro').text()).toContain('Connector')
    expect(dialog.findAll('.access-step').map(step => step.attributes('data-done'))).toEqual(['true', undefined, undefined, 'true'])
    expect(dialog.find('.access-copy-field').exists()).toBe(true)
  })

  it('marks the first step open for an agent without access and the last one without project', async () => {
    const { dialog } = await open(users[2]!)

    expect(dialog.findAll('.access-step').map(step => step.attributes('data-done'))).toEqual(['false', undefined, undefined, 'false'])
    expect(dialog.text()).toContain('Noch kein Zugang.')
  })

  /** The field «Name des Zugangs» (the first field of the dialog is the address of the server). */
  const nameField = (dialog: Awaited<ReturnType<typeof open>>['dialog']) => dialog.findAllComponents({ name: 'FormInput' }).find(field => field.props('label') === 'Name des Zugangs')!
  const needPassword = () => backend.mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: false } : {}))

  it('suggests the name of the environment variable for the access, and counts up when it exists', async () => {
    const { vm } = await open()
    const { vm: other } = await open(users[2]!)

    expect(vm.accessName).toBe('MOLLOPLANER_CLAUDE_TOKEN')
    expect(other.accessName).toBe('MOLLOPLANER_NEU_AGENT_TOKEN')
  })

  it('makes an access without a prompt when the password was confirmed a moment ago, and shows the token once, in this dialog', async () => {
    const { dialog, vm } = await open()

    await vm.createAccess()

    expect(dialog.findComponent(PasswordConfirmField).find('.password-confirm').exists()).toBe(false)
    expect(createAgentToken).toHaveBeenCalledWith({ userId: '3', name: 'MOLLOPLANER_CLAUDE_TOKEN' })
    expect(vm.created).toBe('7|geheim')

    await vi.waitFor(() => expect(dialog.text()).toContain('Wird nicht mehr angezeigt.'))
    expect(dialog.findAll('wa-dialog')).toHaveLength(1)

    await dialog.findAll('wa-button').find(button => button.text() === 'Fertig' && button.element.closest('.wa-stack'))?.trigger('click')
    await vi.waitFor(() => expect(vm.created).toBeNull())
  })

  it('takes the name the person typed, trimmed', async () => {
    const { dialog, vm } = await open()

    nameField(dialog).vm.$emit('update:modelValue', ' Mein Laptop ')
    await vm.createAccess()

    expect(createAgentToken).toHaveBeenCalledWith({ userId: '3', name: 'Mein Laptop' })
  })

  it('refuses an empty name at the field and sends nothing', async () => {
    const { dialog, vm } = await open()

    nameField(dialog).vm.$emit('update:modelValue', '   ')
    await vm.createAccess()

    expect(createAgentToken).not.toHaveBeenCalled()
    expect(dialog.text()).toContain('Name fehlt.')
  })

  it('asks for the password in a field of this dialog when the confirmation is old, then goes on without another click', async () => {
    needPassword()
    const { dialog, vm } = await open()

    await vm.createAccess()
    await vi.waitFor(() => expect(dialog.find('.password-confirm').exists()).toBe(true))

    expect(createAgentToken).not.toHaveBeenCalled()
    expect(dialog.findAll('wa-dialog')).toHaveLength(1)

    dialog.findComponent(PasswordConfirmField).vm.$props.step.setPassword('geheim')
    await dialog.findComponent(PasswordConfirmField).vm.$props.step.submit()

    expect(backend).toHaveBeenCalledWith('/user/confirm-password', { method: 'POST', body: { password: 'geheim' } })
    expect(createAgentToken).toHaveBeenCalledOnce()
    expect(vm.created).toBe('7|geheim')
    expect(dialog.find('.password-confirm').exists()).toBe(false)
  })

  it('makes nothing when the password field is cancelled, and keeps what was typed', async () => {
    needPassword()
    const { dialog, vm } = await open()

    nameField(dialog).vm.$emit('update:modelValue', 'Mein Laptop')
    await vm.createAccess()
    await vi.waitFor(() => expect(dialog.find('.password-confirm').exists()).toBe(true))
    dialog.findComponent(PasswordConfirmField).vm.$props.step.cancel()
    await nextTick()

    expect(dialog.find('.password-confirm').exists()).toBe(false)
    expect(createAgentToken).not.toHaveBeenCalled()
    expect(vm.created).toBeNull()
    expect(vm.accessName).toBe('Mein Laptop')
  })

  it('asks again in the same dialog when the server says the confirmation ran out meanwhile', async () => {
    createAgentToken.mockRejectedValueOnce({ graphQLErrors: [{ message: 'x', extensions: { category: 'validation', validation: { password: ['Bitte bestätige zuerst dein Passwort.'] } } }] })
    const { dialog, vm } = await open()

    await vm.createAccess()
    await vi.waitFor(() => expect(dialog.find('.password-confirm').exists()).toBe(true))

    expect(vm.created).toBeNull()
  })

  it('asks in the row before an access is revoked, never in a second dialog, then revokes it', async () => {
    const { dialog } = await open()

    await dialog.find('.access-row wa-button').trigger('click')

    expect(dialog.findAllComponents(ConfirmModal)).toHaveLength(0)
    const question = dialog.find('.access-question')

    expect(question.text()).toContain('Zugang «Claude Server» widerrufen?')
    expect(revokeAgentToken).not.toHaveBeenCalled()

    await question.findAll('wa-button').find(button => button.text() === 'Widerrufen')!.trigger('click')
    await vi.waitFor(() => expect(revokeAgentToken).toHaveBeenCalledWith({ userId: '3', tokenId: '11' }))
    await vi.waitFor(() => expect(dialog.find('.access-question').exists()).toBe(false))
  })

  it('asks for the password in the row when revoking needs it, and revokes nothing before', async () => {
    needPassword()
    const { dialog } = await open()

    await dialog.find('.access-row wa-button').trigger('click')
    await dialog.find('.access-question').findAll('wa-button').find(button => button.text() === 'Widerrufen')!.trigger('click')
    await vi.waitFor(() => expect(dialog.find('.access-question .password-confirm').exists()).toBe(true))

    expect(revokeAgentToken).not.toHaveBeenCalled()
  })

  it('goes back from the question without revoking', async () => {
    const { dialog } = await open()

    await dialog.find('.access-row wa-button').trigger('click')
    await dialog.find('.access-question').findAll('wa-button').find(button => button.text() === 'Abbrechen')!.trigger('click')

    expect(dialog.find('.access-question').exists()).toBe(false)
    expect(revokeAgentToken).not.toHaveBeenCalled()
  })

  it('lists the projects of the agent with the role and offers only projects the person manages and the agent is not in', async () => {
    const { dialog } = await open()
    const rows = dialog.findAll('.access-row').filter(row => row.text().includes('Alpha'))

    expect(rows).toHaveLength(1)
    expect(rows[0]!.text()).toContain('Agent')

    const offered = dialog.findAllComponents({ name: 'FormSelect' }).find(select => select.props('label') === 'Projekt zuweisen')!

    expect((offered.props('items') as { label: string }[]).map(item => item.label)).toEqual(['Beta'])
  })

  it('assigns a project with the role agent as a default, through the existing mutation', async () => {
    const { vm } = await open()

    vm.newProjectId = '2'
    await vi.waitFor(() => expect(vm.newRole).toBe('agent'))
    await vm.assign()

    expect(setMember).toHaveBeenCalledWith({ projectId: '2', userId: '3', role: 'agent' })
  })

  it('removes the agent from a project through the existing mutation after asking in the row', async () => {
    const { dialog } = await open()

    await dialog.find('[aria-label="Aus Projekt «Alpha» entfernen"]').trigger('click')

    expect(dialog.findAllComponents(ConfirmModal)).toHaveLength(0)
    const question = dialog.find('.access-question')

    expect(question.text()).toContain('Claude aus dem Projekt entfernen?')
    expect(removeMember).not.toHaveBeenCalled()

    await question.findAll('wa-button').find(button => button.text() === 'Entfernen')!.trigger('click')
    await vi.waitFor(() => expect(removeMember).toHaveBeenCalledWith({ projectId: '1', userId: '3' }))
  })
})

describe('admin users: create an agent', () => {
  it('offers to create an agent in the page actions and opens the form', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const button = page.findAll('wa-button').find(candidate => candidate.text() === 'Agent anlegen')!

    expect(page.findComponent(AgentFormModal).props('open')).toBe(false)
    await button.trigger('click')
    expect(page.findComponent(AgentFormModal).props('open')).toBe(true)
  })

  it('closes after creating an agent and opens no second dialog: the new row offers «Zugang einrichten»', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const form = page.findComponent(AgentFormModal)

    await page.findAll('wa-button').find(candidate => candidate.text() === 'Agent anlegen')!.trigger('click')
    form.vm.$emit('created', '4')
    await nextTick()

    expect(page.findComponent(AgentFormModal).props('open')).toBe(false)
    expect(page.findComponent(AgentAccessDialog).exists()).toBe(false)
    expect(page.findAll('.users-row').find(row => row.attributes('data-new') !== undefined)?.text()).toContain('Neu Agent')
    expect(page.find('[data-agent-access="4"]').text()).toBe('Zugang einrichten')

    // The page still moves the focus to the new row a moment later; leave it so that step does not run into the next test.
    page.unmount()
  })

  it('does nothing more after the page is left: no focus on a row that is gone, no step in a finished test', async () => {
    const page = await mountSuspended(AdminUsersPage)
    const form = page.findComponent(AgentFormModal)

    await page.findAll('wa-button').find(candidate => candidate.text() === 'Agent anlegen')!.trigger('click')
    form.vm.$emit('created', '4')
    await nextTick()
    page.unmount()

    const query = vi.spyOn(document, 'querySelector')
    await new Promise(resolve => setTimeout(resolve, 400))

    expect(query.mock.calls.filter(([selector]) => String(selector).includes('data-agent-access'))).toEqual([])
    query.mockRestore()
  })

  it('creates the agent and reports its id, without a prompt when the password was confirmed a moment ago', async () => {
    const form = await mountSuspended(AgentFormModal, { props: { open: true } })

    Object.assign((form.vm as unknown as { state: { name: string, email: string } }).state, { name: ' Claude Neu ', email: ' neu@example.ch ' })
    await form.find('form').trigger('submit')

    await vi.waitFor(() => expect(form.emitted('created')).toEqual([['9']]))
    expect(createAgent).toHaveBeenCalledWith({ input: { name: 'Claude Neu', email: 'neu@example.ch' } })
  })

  it('asks for the password in a field of its own dialog, and creates nothing when it is cancelled', async () => {
    backend.mockImplementation(async (url: string) => (url === '/user/confirmed-password-status' ? { confirmed: false } : {}))
    const form = await mountSuspended(AgentFormModal, { props: { open: true } })

    await form.find('form').trigger('submit')
    await vi.waitFor(() => expect(form.find('.password-confirm').exists()).toBe(true))

    expect(createAgent).not.toHaveBeenCalled()

    form.findComponent(PasswordConfirmField).vm.$props.step.cancel()
    await nextTick()

    expect(form.find('.password-confirm').exists()).toBe(false)
    expect(createAgent).not.toHaveBeenCalled()
    expect(form.emitted('created')).toBeUndefined()
  })
})
