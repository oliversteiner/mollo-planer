import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, h, ref } from 'vue'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import ExportMenu from '~/components/document/ExportMenu.vue'
import ShareDialog from '~/components/document/ShareDialog.vue'
import TransferDialog from '~/components/document/TransferDialog.vue'
import { installElementInternals } from './support/element-internals'

const { mutations, files, letterhead } = vi.hoisted(() => ({
  /** Whether a letterhead is uploaded (query LetterheadAvailable, Auftrag 39 F). */
  letterhead: { available: false },
  mutations: { SetDocumentSharing: vi.fn(), TransferDocument: vi.fn(), ExportDocumentPdf: vi.fn() } as Record<string, ReturnType<typeof vi.fn>>,
  files: { text: vi.fn(), blob: vi.fn() }
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({
    result: ref({ project: { id: '7', members: [
      { role: { key: 'owner' }, user: { id: '2', name: 'Ben Müller', avatarUrl: null, isAgent: false } },
      { role: { key: 'member' }, user: { id: '3', name: 'Anna Keller', avatarUrl: null, isAgent: false } },
      { role: { key: 'agent' }, user: { id: '4', name: 'Kevin', avatarUrl: null, isAgent: true } }
    ] }, letterheadAvailable: letterhead.available }),
    loading: ref(false)
  }),
  useMutation: (document: { definitions?: { name?: { value: string } }[] }) => ({ mutate: mutations[document?.definitions?.[0]?.name?.value ?? ''] ?? vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
vi.mock('~/utils/planer', async importOriginal => ({ ...(await importOriginal<typeof import('~/utils/planer')>()), downloadText: files.text, downloadBlob: files.blob }))
mockNuxtImport('useProject', () => () => ({ projectId: computed(() => '7'), project: computed(() => ({ id: '7' })), loaded: computed(() => true), can: () => true }))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  Object.values(mutations).forEach(mutation => mutation.mockReset())
  files.text.mockReset()
  files.blob.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('useLocalDraft', () => {
  it('writes, reads and deletes a draft', () => {
    const draft = useLocalDraft<{ title: string }>(() => 'draft:1')

    draft.write(3, { title: 'Entwurf' })

    expect(draft.read()).toMatchObject({ revision: 3, value: { title: 'Entwurf' } })

    draft.clear()

    expect(draft.read()).toBeNull()
  })

  it('does not break when the storage throws, and ignores a damaged entry', () => {
    const draft = useLocalDraft<string>(() => 'draft:2')
    window.localStorage.setItem('draft:2', '{kaputt')

    expect(draft.read()).toBeNull()

    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('full')
    })
    vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
      throw new Error('denied')
    })

    expect(draft.read()).toBeNull()
    expect(() => draft.write(1, 'x')).not.toThrow()
    expect(() => draft.clear()).not.toThrow()
  })
})

describe('useUnsavedChanges', () => {
  const host = (dirty: () => boolean) => {
    let api!: ReturnType<typeof useUnsavedChanges>
    const component = defineComponent(() => {
      api = useUnsavedChanges(dirty)

      return () => h('div')
    })

    return { component, api: () => api }
  }

  it('asks the browser before the tab is closed, only while there are unsaved changes', async () => {
    const dirty = ref(true)
    const { component } = host(() => dirty.value)
    await mountSuspended(component)

    const closing = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(closing)

    expect(closing.defaultPrevented).toBe(true)

    dirty.value = false
    const quiet = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(quiet)

    expect(quiet.defaultPrevented).toBe(false)
  })

  it('holds the navigation with unsaved changes and lets it go on "Verlassen"', async () => {
    const { component, api } = host(() => true)
    await mountSuspended(component)

    const decision = api().guard()

    expect(api().open.value).toBe(true)

    await api().confirm()

    expect(await decision).toBe(true)
    expect(api().open.value).toBe(false)
  })

  it('stays on "Bleiben"', async () => {
    const { component, api } = host(() => true)
    await mountSuspended(component)

    const decision = api().guard()
    api().stay()

    expect(await decision).toBe(false)
    expect(api().open.value).toBe(false)
  })

  it('lets go at once when there is nothing unsaved', async () => {
    const { component, api } = host(() => false)
    await mountSuspended(component)

    expect(api().guard()).toBe(true)
    expect(api().open.value).toBe(false)
  })
})

describe('document sharing (task 17 D8)', () => {
  const document = { id: '5', projectAccess: 'READ' as const, shares: [{ user: { id: '3', name: 'Anna Keller', isAgent: false }, access: 'EDIT' as const }] }

  it('offers only members of the project that have no share yet, and sends the chosen levels', async () => {
    mutations.SetDocumentSharing!.mockResolvedValue({ data: { setDocumentSharing: { id: '5' } } })
    const dialog = await mountSuspended(ShareDialog, { props: { open: true, projectId: '7', document } })
    await flushPromises()
    const vm = dialog.vm as unknown as { groups: { people: { id: string }[] }[], submit: () => Promise<void> }

    expect(vm.groups[0]!.people.map(person => person.id)).toEqual(['2', '4'])

    await vm.submit()

    expect(mutations.SetDocumentSharing).toHaveBeenCalledWith({ id: '5', input: { projectAccess: 'READ', shares: [{ userId: '3', access: 'EDIT' }] } })
  })

  it('hands the document to a member, not offering the current owner', async () => {
    mutations.TransferDocument!.mockResolvedValue({ data: { transferDocument: { id: '5' } } })
    const dialog = await mountSuspended(TransferDialog, { props: { open: true, projectId: '7', documentId: '5', ownerId: '2' } })
    await flushPromises()
    const vm = dialog.vm as unknown as { groups: { people: { id: string }[] }[], newOwner: string | null, submit: () => Promise<void> }

    expect(vm.groups[0]!.people.map(person => person.id)).toEqual(['3', '4'])

    vm.newOwner = '3'
    await vm.submit()

    expect(mutations.TransferDocument).toHaveBeenCalledWith({ id: '5', ownerId: '3' })
  })
})

describe('document export (task 17 D8)', () => {
  const document = { id: '5', title: 'Aufbau Bühne', content: '# Aufbau' }
  const items = (menu: Awaited<ReturnType<typeof mountSuspended>>) => (menu.findComponent({ name: 'ActionMenu' }).props('items') as ActionMenuItem[])

  it('gives Markdown from the content in the browser', async () => {
    const menu = await mountSuspended(ExportMenu, { props: { document } })

    items(menu)[0]!.onSelect?.()

    expect(files.text).toHaveBeenCalledWith('aufbau-buhne.md', '# Aufbau', 'text/markdown;charset=utf-8')
  })

  it('gives the PDF the server made, and shows that it is being made', async () => {
    mutations.ExportDocumentPdf!.mockResolvedValue({ data: { exportDocumentPdf: { fileName: 'aufbau.pdf', mimeType: 'application/pdf', contentBase64: btoa('%PDF-1.4') } } })
    const menu = await mountSuspended(ExportMenu, { props: { document } })

    items(menu)[1]!.onSelect?.()
    await flushPromises()

    expect(mutations.ExportDocumentPdf).toHaveBeenCalledWith({ id: '5', withLetterhead: false })
    expect(files.blob).toHaveBeenCalledOnce()
    expect(files.blob.mock.calls[0]![0]).toBe('aufbau.pdf')
    expect((files.blob.mock.calls[0]![1] as Blob).type).toBe('application/pdf')
  })

  it('offers the PDF with the letterhead only when one is uploaded (Auftrag 39 F)', async () => {
    mutations.ExportDocumentPdf!.mockResolvedValue({ data: { exportDocumentPdf: { fileName: 'aufbau.pdf', mimeType: 'application/pdf', contentBase64: btoa('%PDF-1.4') } } })

    letterhead.available = false
    expect(items(await mountSuspended(ExportMenu, { props: { document } }))).toHaveLength(2)

    letterhead.available = true
    const menu = await mountSuspended(ExportMenu, { props: { document } })
    letterhead.available = false

    expect(items(menu)).toHaveLength(3)
    expect(items(menu)[2]!.label).toBe('PDF mit Briefpapier')
    items(menu)[2]!.onSelect?.()
    await flushPromises()

    expect(mutations.ExportDocumentPdf).toHaveBeenCalledWith({ id: '5', withLetterhead: true })
    expect(files.blob).toHaveBeenCalledOnce()
  })

  it('says so when the PDF could not be made', async () => {
    mutations.ExportDocumentPdf!.mockRejectedValue(new Error('down'))
    const menu = await mountSuspended(ExportMenu, { props: { document } })

    items(menu)[1]!.onSelect?.()
    await flushPromises()

    expect(files.blob).not.toHaveBeenCalled()
  })
})
