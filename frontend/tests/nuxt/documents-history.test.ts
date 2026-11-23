import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import ConfirmModal from '~/components/ConfirmModal.vue'
import Diff from '~/components/document/Diff.vue'
import HistoryPage from '~/pages/projects/[id]/documents/[docId]/history.vue'
import { installElementInternals } from './support/element-internals'

const { state, restore, loads } = vi.hoisted(() => ({
  state: { viewerAccess: 'EDIT' as string },
  restore: vi.fn(),
  loads: [] as { documentId: string, revision: number }[]
}))

const versions = [
  { revision: 3, title: 'Aufbau', kind: 'PROCEDURE', restoredFrom: 1, createdAt: '2026-11-18T10:00:00Z', createdBy: { id: '2', name: 'Ben Müller' } },
  { revision: 2, title: 'Aufbau', kind: 'PROCEDURE', restoredFrom: null, createdAt: '2026-11-17T10:00:00Z', createdBy: { id: '3', name: 'Anna Keller' } },
  { revision: 1, title: 'Aufbau', kind: 'PROCEDURE', restoredFrom: null, createdAt: '2026-11-16T10:00:00Z', createdBy: null }
]
const contents: Record<number, string> = { 1: 'Zwei Stunden\n', 2: 'Zwei Stunden\nPause\n', 3: 'Drei <b>Stunden</b>\n' }

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (document: { definitions?: { name?: { value: string } }[] }, variables: () => { documentId?: string, revision?: number }, options?: () => { enabled?: boolean }) => {
    const name = document?.definitions?.[0]?.name?.value

    if (name === 'DocumentVersions') {
      return { result: ref({ document: { id: '5', title: 'Aufbau', revision: 3, viewerAccess: state.viewerAccess, versions } }), loading: ref(false) }
    }
    const result = computed(() => {
      if (options?.().enabled === false) {
        return undefined
      }
      const { revision, documentId } = variables()
      loads.push({ documentId: documentId!, revision: revision! })

      return { documentVersion: { revision, title: 'Aufbau', content: contents[revision!] } }
    })

    return { result, loading: ref(false) }
  },
  useMutation: () => ({ mutate: restore }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7' })), loaded: computed(() => true), can: () => true }))
mockNuxtImport('useRoute', () => () => ({ params: { docId: '5' }, query: {}, path: '/projects/7/documents/5/history' }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  state.viewerAccess = 'EDIT'
  restore.mockReset().mockResolvedValue({ data: { restoreDocumentVersion: { id: '5', revision: 4 } } })
  loads.length = 0
})

interface HistoryVm {
  toggle: (revision: number, checked: boolean) => void
  chosen: number[]
  restoring: number | null
  confirmRestore: () => Promise<void>
}

const mountPage = async () => mountSuspended(HistoryPage, { global: { stubs: { Teleport: true } } })

describe('document history (task 17 D10)', () => {
  it('lists the versions with person, time and where a restored one comes from, without loading any content', async () => {
    const page = await mountPage()
    const rows = page.findAll('.history-row')

    expect(rows).toHaveLength(3)
    expect(rows[0]!.text()).toContain('Fassung 3')
    expect(rows[0]!.text()).toContain('von Ben Müller')
    expect(rows[0]!.text()).toContain('zurückgeholt aus Fassung 1')
    expect(rows[2]!.text()).not.toContain('von ')
    expect(loads).toEqual([])
  })

  it('loads the content of the two chosen versions only and shows the difference as text', async () => {
    const page = await mountPage()
    const vm = page.vm as unknown as HistoryVm

    vm.toggle(2, true)
    await flushPromises()

    expect(loads).toEqual([])

    vm.toggle(3, true)
    await flushPromises()

    expect([...new Set(loads.map(load => load.revision))].sort()).toEqual([2, 3])
    expect(page.findComponent(Diff).exists()).toBe(true)
    expect(page.find('.document-diff').text()).toContain('<b>Stunden</b>')
    expect(page.find('.document-diff b').exists()).toBe(false)
    expect(page.find('.document-diff').text()).toContain('+')
    expect(page.find('.document-diff').text()).toContain('−')
  })

  it('lets a third choice replace the one chosen first', async () => {
    const page = await mountPage()
    const vm = page.vm as unknown as HistoryVm

    vm.toggle(1, true)
    vm.toggle(2, true)
    vm.toggle(3, true)

    expect(vm.chosen).toEqual([2, 3])
  })

  it('brings a version back through a question, as a new version', async () => {
    const page = await mountPage()
    const vm = page.vm as unknown as HistoryVm

    vm.restoring = 2
    await flushPromises()

    const modal = page.findAllComponents(ConfirmModal).find(candidate => candidate.props('open'))!

    expect(modal.props('title')).toBe('Fassung 2 zurückholen?')
    expect(restore).not.toHaveBeenCalled()

    await modal.props('action')()

    expect(restore).toHaveBeenCalledWith({ id: '5', revision: 2 })
  })

  it('offers "Zurückholen" only with the level EDIT', async () => {
    state.viewerAccess = 'READ'
    const reader = await mountPage()

    expect(reader.find('.history-restore').exists()).toBe(false)

    state.viewerAccess = 'EDIT'
    const editor = await mountPage()

    expect(editor.findAll('.history-restore')).toHaveLength(3)
  })
})
