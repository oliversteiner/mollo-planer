import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import DocumentPage from '~/pages/projects/[id]/documents/[docId]/index.vue'
import DocumentsList from '~/pages/projects/[id]/documents/index.vue'
import HistoryPage from '~/pages/projects/[id]/documents/[docId]/history.vue'
import { installElementInternals } from './support/element-internals'

const { commands, view, leave } = vi.hoisted(() => ({
  commands: [] as { id: string, label: string, visible?: () => boolean, run: () => void }[],
  view: { focusing: false },
  leave: vi.fn()
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (document: { definitions?: { name?: { value: string } }[] }) => {
    const name = document?.definitions?.[0]?.name?.value
    const doc = { id: '5', title: 'Aufbau', kind: 'PROCEDURE', content: '# A', contentJson: { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Text' }] }] }, projectAccess: 'READ', viewerAccess: 'MANAGE', revision: 1, createdAt: '2026-11-10T10:00:00Z', updatedAt: '2026-11-18T10:00:00Z', deletionId: null, project: { id: '7', name: 'P' }, owner: { id: '2', name: 'Ben', avatarUrl: null, isAgent: false }, updatedBy: null, shares: [], versions: [] }

    return { result: ref(name === 'Documents' ? { documents: [{ ...doc, updatedBy: null }] } : { document: doc }), loading: ref(false), refetch: vi.fn() }
  },
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7' })), loaded: computed(() => true), can: () => true }))
mockNuxtImport('useRoute', () => () => ({ params: { docId: '5' }, query: {}, path: '/projects/7/documents/5' }))
mockNuxtImport('useViewMode', () => () => ({ mode: ref('full'), calm: ref(false), focusing: computed(() => view.focusing), setMode: vi.fn(), toggleCalm: vi.fn(), enterFocus: vi.fn(), leaveFocus: leave }))
mockNuxtImport('useCommands', () => (factory: () => typeof commands) => {
  commands.length = 0
  commands.push(...factory())
})

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  commands.length = 0
  view.focusing = false
  leave.mockReset()
})

const mounted: { unmount: () => void }[] = []

/** The pages listen on the window (Escape, Mod+S), so every page is taken down again after the test. */
const mountWith = async (page: Parameters<typeof mountSuspended>[0]) => {
  const wrapper = await mountSuspended(page, { global: { stubs: { Teleport: true } } })
  mounted.push(wrapper)

  return wrapper
}

afterEach(() => {
  mounted.splice(0).forEach(wrapper => wrapper.unmount())
})

describe('documents in the calm view (task 17 D11)', () => {
  it('marks the filter row, the meta line and the secondary actions to go in the calm view and the rows as rows', async () => {
    const list = await mountWith(DocumentsList)

    expect(list.findAll('[data-zen="hide"]').length).toBeGreaterThan(0)
    expect(list.findAll('[data-zen-row]')).toHaveLength(1)

    const page = await mountWith(DocumentPage)

    expect(page.find('.document-meta[data-zen="hide"]').exists()).toBe(true)

    const history = await mountWith(HistoryPage)

    expect(history.findAll('[data-zen-row]').length).toBe(0)
  })

  it('keeps everything the calm view hides reachable through the palette', async () => {
    await mountWith(DocumentPage)

    const visible = commands.filter(command => command.visible?.() !== false).map(command => command.id)

    expect(visible).toEqual(expect.arrayContaining(['document-focus', 'document-history', 'document-export-markdown', 'document-export-pdf', 'document-share', 'document-transfer']))
  })

  it('offers "Fokus" with the document title and shows the back button only in the focus view', async () => {
    const page = await mountWith(DocumentPage)

    expect(commands.find(command => command.id === 'document-focus')!.label).toContain('Aufbau')
    expect(page.find('[data-zen="only"]').exists()).toBe(false)

    view.focusing = true
    const focused = await mountWith(DocumentPage)

    expect(focused.find('[data-zen="only"]').text()).toContain('Zurück')
    expect(commands.find(command => command.id === 'document-focus')!.visible?.()).toBe(false)
  })

  it('leaves the focus view with Escape when nothing is unsaved, and asks first when there is', async () => {
    view.focusing = true
    const page = await mountWith(DocumentPage)
    await flushPromises()

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()

    expect(leave).toHaveBeenCalledOnce()

    leave.mockReset()
    const vm = page.vm as unknown as { startEditing: () => void, typed: () => void, unsaved: { open: { value: boolean } } }
    vm.startEditing()
    vm.typed()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()

    expect(leave).not.toHaveBeenCalled()
    expect(vm.unsaved.open.value).toBe(true)
  })

  it('shows the saving state in the zen frame while editing', async () => {
    const page = await mountWith(DocumentPage)
    const vm = page.vm as unknown as { startEditing: () => void, typed: () => void }

    expect(page.text()).not.toContain('Nicht gesichert Gesichert')

    vm.startEditing()
    await flushPromises()

    expect(page.text()).toContain('Gesichert')

    vm.typed()
    await flushPromises()

    expect(page.text()).toContain('Nicht gesichert')
  })
})
