import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import ConflictDialog from '~/components/document/ConflictDialog.vue'
import DocumentPage from '~/pages/projects/[id]/documents/[docId]/index.vue'
import { installElementInternals } from './support/element-internals'

const { state, mutations } = vi.hoisted(() => ({
  state: { document: undefined as unknown },
  mutations: { UpdateDocument: vi.fn(), CreateDocument: vi.fn(), DeleteDocument: vi.fn() } as Record<string, ReturnType<typeof vi.fn>>
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref({ document: state.document }), loading: ref(false), refetch: vi.fn() }),
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => ({ mutate: mutations[document?.definitions?.[0]?.name?.value ?? ''] ?? vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7' })), loaded: computed(() => true), can: () => true }))
mockNuxtImport('useRoute', () => () => ({ params: { docId: '5' }, query: {}, path: '/projects/7/documents/5' }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const doc = (overrides: Record<string, unknown> = {}) => ({
  id: '5',
  title: 'Aufbau Bühne',
  kind: 'PROCEDURE',
  content: '# Aufbau',
  contentJson: { type: 'doc', content: [{ type: 'heading', attrs: { level: 1 }, content: [{ type: 'text', text: 'Aufbau' }] }] },
  projectAccess: 'READ',
  viewerAccess: 'READ',
  revision: 3,
  createdAt: '2026-11-10T10:00:00Z',
  updatedAt: '2026-11-18T10:00:00Z',
  deletionId: null,
  project: { id: '7', name: 'Podcast' },
  owner: { id: '2', name: 'Ben Müller', avatarUrl: null, isAgent: false },
  updatedBy: { id: '3', name: 'Anna Keller' },
  shares: [],
  ...overrides
})

interface PageVm {
  startEditing: () => void
  typed: () => void
  save: () => Promise<void>
  editing: boolean
  dirty: boolean
  title: string
  conflict: boolean
  saveError: string
  offeredDraft: unknown
  moreItems: ActionMenuItem[][]
}

const mountPage = async () => mountSuspended(DocumentPage, { global: { stubs: { Teleport: true } } })
const labels = (items: ActionMenuItem[][]) => items.flat().map(item => item.label)
const conflictError = Object.assign(new Error('conflict'), { graphQLErrors: [{ message: 'conflict', extensions: { code: 'DOCUMENT_CONFLICT', currentRevision: 4 } }] })
const validationError = Object.assign(new Error('refused'), { graphQLErrors: [{ message: 'refused', extensions: { category: 'validation', validation: { 'input.contentJson': ['Dieses Element ist im Dokument nicht erlaubt.'] } } }] })

beforeEach(() => {
  window.localStorage.clear()
  Object.values(mutations).forEach(mutation => mutation.mockReset())
  mutations.UpdateDocument!.mockResolvedValue({ data: { updateDocument: { id: '5', revision: 4 } } })
})

describe('document page: reading (task 17 D6)', () => {
  it('shows title, kind, owner and the content; a reader has neither "Bearbeiten" nor the management entries', async () => {
    state.document = doc()
    const page = await mountPage()

    expect(page.find('.document-title').text()).toBe('Aufbau Bühne')
    expect(page.text()).toContain('Vorgehensweise')
    expect(page.text()).toContain('Owner: Ben Müller')
    expect(page.find('.ProseMirror h1').exists()).toBe(true)
    expect(page.text()).not.toContain('Bearbeiten')
    expect(page.text()).toContain('Exportieren')
    expect(labels((page.vm as unknown as PageVm).moreItems)).toEqual(['Verlauf'])
  })

  it('offers "Bearbeiten" with the level EDIT and the management entries only with MANAGE', async () => {
    state.document = doc({ viewerAccess: 'EDIT' })
    const editor = await mountPage()

    expect(editor.text()).toContain('Bearbeiten')
    expect(labels((editor.vm as unknown as PageVm).moreItems)).toEqual(['Verlauf'])

    state.document = doc({ viewerAccess: 'MANAGE' })
    const manager = await mountPage()

    expect(labels((manager.vm as unknown as PageVm).moreItems)).toEqual(['Verlauf', 'Freigeben', 'Owner übergeben', 'Löschen'])
  })

  it('says so when the document is not found', async () => {
    state.document = null
    const page = await mountPage()

    expect(page.text()).toContain('Dokument nicht gefunden')
  })
})

describe('document page: editing and saving (task 17 D7)', () => {
  it('opens the editor with the profile "document" and saves with the loaded revision', async () => {
    state.document = doc({ viewerAccess: 'EDIT' })
    const page = await mountPage()
    const vm = page.vm as unknown as PageVm

    vm.startEditing()
    await flushPromises()

    expect(page.find('[data-profile="document"]').exists()).toBe(true)

    vm.title = 'Neuer Titel'
    vm.typed()
    await vm.save()

    expect(mutations.UpdateDocument).toHaveBeenCalledOnce()
    expect(mutations.UpdateDocument!.mock.calls[0]![0]).toMatchObject({ id: '5', revision: 3, input: { title: 'Neuer Titel', kind: 'PROCEDURE' } })
    expect(vm.dirty).toBe(false)
  })

  it('keeps a draft in this browser while typing and deletes it after saving', async () => {
    state.document = doc({ viewerAccess: 'EDIT' })
    const page = await mountPage()
    const vm = page.vm as unknown as PageVm
    vi.useFakeTimers()

    vm.startEditing()
    vm.title = 'Entwurf'
    vm.typed()
    await flushPromises()
    vi.advanceTimersByTime(600)
    vi.useRealTimers()

    const stored = JSON.parse(window.localStorage.getItem('mollo-planer:document-draft:5') ?? 'null')

    expect(stored.revision).toBe(3)
    expect(stored.value.title).toBe('Entwurf')

    await vm.save()

    expect(window.localStorage.getItem('mollo-planer:document-draft:5')).toBeNull()
  })

  it('offers an unsaved draft when editing starts', async () => {
    window.localStorage.setItem('mollo-planer:document-draft:5', JSON.stringify({ savedAt: '2026-11-19T09:00:00Z', revision: 3, value: { title: 'Aus dem Entwurf', kind: 'PROCEDURE', contentJson: null } }))
    state.document = doc({ viewerAccess: 'EDIT' })
    const page = await mountPage()
    const vm = page.vm as unknown as PageVm

    vm.startEditing()
    await flushPromises()

    expect(vm.offeredDraft).not.toBeNull()
    expect(page.findAllComponents(ConfirmModal).some(modal => modal.props('open') && modal.props('title') === 'Ungesicherten Entwurf wiederherstellen?')).toBe(true)
  })

  it('shows the conflict dialog and keeps the own text as a draft when somebody saved in the meantime', async () => {
    mutations.UpdateDocument!.mockRejectedValue(conflictError)
    state.document = doc({ viewerAccess: 'EDIT' })
    const page = await mountPage()
    const vm = page.vm as unknown as PageVm

    vm.startEditing()
    vm.title = 'Meine Fassung'
    vm.typed()
    await vm.save()
    await flushPromises()

    expect(vm.conflict).toBe(true)
    expect(page.findComponent(ConflictDialog).props('open')).toBe(true)
    expect(page.text()).toContain('Jemand hat eine neuere Fassung gesichert')
    expect(page.text()).toContain('Als neues Dokument sichern')
    expect(JSON.parse(window.localStorage.getItem('mollo-planer:document-draft:5') ?? 'null').value.title).toBe('Meine Fassung')
  })

  it('shows what the server refused about the content and never swallows it', async () => {
    mutations.UpdateDocument!.mockRejectedValue(validationError)
    state.document = doc({ viewerAccess: 'EDIT' })
    const page = await mountPage()
    const vm = page.vm as unknown as PageVm

    vm.startEditing()
    vm.typed()
    await vm.save()
    await flushPromises()

    expect(vm.saveError).toBe('Dieses Element ist im Dokument nicht erlaubt.')
    expect(page.text()).toContain('Dieses Element ist im Dokument nicht erlaubt.')
    expect(vm.dirty).toBe(true)
  })
})
