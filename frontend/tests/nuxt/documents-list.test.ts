import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import EmptyState from '~/components/EmptyState.vue'
import DocumentsPage from '~/pages/projects/[id]/documents/index.vue'
import ProjectTabs from '~/components/ProjectTabs.vue'
import { installElementInternals } from './support/element-internals'

const { answer, rights, queries } = vi.hoisted(() => ({
  answer: { value: undefined as unknown },
  rights: { value: [] as string[] },
  queries: [] as unknown[]
}))

vi.mock('@vue/apollo-composable', () => ({
  useQuery: (_document: unknown, variables: () => unknown) => {
    queries.push(variables())

    return { result: ref(answer.value), loading: ref(false), refetch: vi.fn() }
  },
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))
mockNuxtImport('useProject', () => () => ({
  projectId: computed(() => '7'),
  project: computed(() => ({ id: '7' })),
  loaded: computed(() => true),
  can: (right: string) => rights.value.includes(right)
}))

installElementInternals()

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const document = (overrides: Record<string, unknown> = {}) => ({
  id: '1',
  title: 'Aufbau Bühne',
  kind: 'PROCEDURE',
  projectAccess: 'READ',
  viewerAccess: 'READ',
  revision: 3,
  updatedAt: '2026-11-18T10:00:00Z',
  owner: { id: '2', name: 'Ben Müller', avatarUrl: null, isAgent: false },
  updatedBy: { id: '3', name: 'Anna Keller' },
  ...overrides
})

describe('documents list (task 17 D6)', () => {
  it('shows title, kind, owner, who may do what and the last change; a row opens the document', async () => {
    answer.value = { documents: [document(), document({ id: '2', title: 'Notizen', kind: 'MINUTES', projectAccess: 'NONE', owner: null, updatedBy: null })] }
    const page = await mountSuspended(DocumentsPage)
    const rows = page.findAll('.documents-row')

    expect(rows).toHaveLength(2)
    expect(rows[0]!.text()).toContain('Aufbau Bühne')
    expect(rows[0]!.text()).toContain('Vorgehensweise')
    expect(rows[0]!.text()).toContain('Projekt liest')
    expect(rows[0]!.text()).toContain('Owner: Ben Müller')
    expect(rows[0]!.text()).toContain('von Anna Keller')
    expect(rows[0]!.find('a').attributes('href')).toBe('/projects/7/documents/1')
    expect(rows[1]!.text()).toContain('Privat')
    expect(rows[1]!.text()).toContain('Kein Owner')
    expect(rows.every(row => row.attributes('data-zen-row') !== undefined)).toBe(true)
  })

  it('offers "Dokument anlegen" only with document.create', async () => {
    answer.value = { documents: [] }
    rights.value = []
    const without = await mountSuspended(DocumentsPage)

    expect(without.text()).not.toContain('Dokument anlegen')

    rights.value = ['DOCUMENT_CREATE']
    const withRight = await mountSuspended(DocumentsPage)

    expect(withRight.findComponent(EmptyState).text()).toContain('Dokument anlegen')
    rights.value = []
  })

  it('shows an empty state with a hint for a project without documents', async () => {
    answer.value = { documents: [] }
    const page = await mountSuspended(DocumentsPage)

    expect(page.findComponent(EmptyState).text()).toContain('Noch keine Dokumente')
    expect(page.find('.documents-list').exists()).toBe(false)
  })

  it('asks the server for the kind and hides the filter row in the calm view', async () => {
    answer.value = { documents: [document()] }
    queries.length = 0
    const page = await mountSuspended(DocumentsPage)

    expect(queries[0]).toEqual({ projectId: '7' })
    expect(page.find('[data-zen="hide"]').exists()).toBe(true)
  })

  it('has the tab "Dokumente" after "Tasks" and before "Mitglieder"', async () => {
    const tabs = await mountSuspended(ProjectTabs, { props: { projectId: '7' } })

    expect(tabs.findAll('a').map(link => link.text()).slice(2, 5)).toEqual(['Tasks', 'Dokumente', 'Mitglieder'])
    expect(tabs.findAll('a')[3]!.attributes('href')).toBe('/projects/7/documents')
  })
})
