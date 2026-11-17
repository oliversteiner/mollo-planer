import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import TaskCard from '~/components/task/Card.vue'
import KanbanPage from '~/pages/projects/[id]/kanban.vue'

/** The card menu offers "Löschen" where the backend allows it for this task (F5.3). */
function card(id: string, viewerCanDelete: boolean) {
  return {
    id,
    title: `Task ${id}`,
    icon: null,
    status: 'OPEN',
    boardPosition: Number(id),
    dueDate: null,
    commit: null,
    userStory: null,
    commentCount: 0,
    labels: [],
    assignee: null,
    todo: { id: '3', title: 'Programm' },
    viewerCanUpdate: true,
    viewerCanDelete
  }
}

const answer = ref({
  kanban: [{ status: 'OPEN', tasks: [card('1', true), card('2', false)] }],
  project: { id: '1', members: [] },
  todos: []
})

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: answer, loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

mockNuxtImport('useProject', () => () => ({
  projectId: computed(() => '1'),
  project: computed(() => ({ id: '1', labelSet: null })),
  loading: ref(false),
  loaded: computed(() => true),
  can: (right: string) => right === 'TASK_MOVE'
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('Kanban card menu', () => {
  async function menus(): Promise<ActionMenuItem[][]> {
    const page = await mountSuspended(KanbanPage, { global: { stubs: { TaskDetail: true, TaskFormModal: true, Teleport: true } } })
    await flushPromises()

    return page.findAllComponents(TaskCard).map(found => found.props('menuItems') as ActionMenuItem[])
  }

  it('ends with a red "Löschen" when the task may be deleted', async () => {
    const [own] = await menus()

    expect(own?.at(-1)).toMatchObject({ label: 'Löschen', icon: 'trash', color: 'error' })
  })

  it('has no "Löschen" otherwise', async () => {
    const [, foreign] = await menus()

    expect(foreign?.some(item => item.label === 'Löschen')).toBe(false)
  })
})
