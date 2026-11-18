import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { ActionMenuItem } from '~/components/ActionMenu.vue'
import ActionMenu from '~/components/ActionMenu.vue'
import Timeline from '~/components/project/Timeline.vue'

/** The task menu of the timeline is the one of the Kanban card: edit, place in the todo, delete (F7.6). */
function task(id: string, viewerCanUpdate: boolean, viewerCanDelete: boolean) {
  return { id, title: `Task ${id}`, description: null, icon: null, status: 'OPEN', dueDate: null, userStory: null, commentCount: 0, labels: [], assignee: null, viewerCanUpdate, viewerCanDelete }
}

const answer = ref({
  project: {
    id: '1',
    milestones: [{
      id: '1', title: 'Start', description: null, icon: null, dueDate: null, release: null, status: 'OPEN', progress: { done: 0, total: 2, percent: 0 },
      todos: [{ id: '1', title: 'Arbeit', description: null, icon: null, dueDate: null, status: 'OPEN', progress: { done: 0, total: 2, percent: 0 }, tasks: [task('1', true, true), task('2', false, false)] }]
    }]
  }
})

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: answer, loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('Timeline task menu', () => {
  async function menus(): Promise<ActionMenuItem[][]> {
    const page = await mountSuspended(Timeline, { props: { projectId: '1', canReorderTask: true } })
    await flushPromises()
    // The todo is closed at first: open it to see its tasks.
    await page.findAll('button').find(button => button.text().includes('Arbeit'))?.trigger('click')
    await flushPromises()

    return page.findAllComponents(ActionMenu).map(found => found.props('items') as ActionMenuItem[]).filter(items => items.some(item => ['Nach oben', 'Nach unten', 'Ändern', 'Löschen'].includes(item.label)))
  }

  it('has edit, the place in the todo and a red delete for a task the person may change and delete', async () => {
    const [own] = await menus()

    expect(own?.map(item => item.label)).toEqual(['Ändern', 'Nach unten', 'Löschen'])
    expect(own?.at(-1)).toMatchObject({ icon: 'trash', color: 'error' })
  })

  it('has only the place in the todo for a task the person may not change or delete', async () => {
    const menusFound = await menus()

    expect(menusFound[1]?.map(item => item.label)).toEqual(['Nach oben'])
  })
})
