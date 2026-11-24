import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import Timeline from '~/components/project/Timeline.vue'

/** A task of the timeline is moved only by its handle, never by the row (docs/verwaltung-agenten-anfasser.md §3.5). */
const answer = ref({
  project: {
    id: '1',
    milestones: [{
      id: '1', title: 'Start', description: null, icon: null, dueDate: null, release: null, status: 'OPEN', progress: { done: 0, total: 1, percent: 0 },
      todos: [{
        id: '1', title: 'Arbeit', description: null, icon: null, dueDate: null, status: 'OPEN', progress: { done: 0, total: 1, percent: 0 },
        tasks: [{ id: '1', title: 'Task 1', description: null, icon: null, status: 'OPEN', dueDate: null, userStory: null, commentCount: 0, labels: [], assignee: null, viewerCanUpdate: true, viewerCanDelete: true }]
      }]
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

async function mountTimeline(canMoveTask: boolean) {
  const page = await mountSuspended(Timeline, { props: { projectId: '1', canMoveTask } })
  await flushPromises()
  await page.findAll('button').find(button => button.text().includes('Arbeit'))?.trigger('click')
  await flushPromises()

  return page
}

describe('Timeline task handle', () => {
  it('drags only by the handle', async () => {
    const page = await mountTimeline(true)

    expect(page.findComponent(VueDraggable).props('handle')).toBe('.task-handle')
  })

  it('shows the handle with a label when the person may move tasks', async () => {
    const page = await mountTimeline(true)
    const handle = page.find('.task-handle')

    expect(handle.exists()).toBe(true)
    expect(handle.attributes('aria-label')).toBe('Task verschieben')
    expect(handle.attributes('data-zen')).toBe('hide')
  })

  it('shows no handle without the right to move', async () => {
    const page = await mountTimeline(false)

    expect(page.find('.task-handle').exists()).toBe(false)
  })

  it('does not open the task by a click on the handle', async () => {
    const page = await mountTimeline(true)

    await page.find('.task-handle').trigger('click')

    expect(page.emitted('openTask')).toBeUndefined()
  })
})
