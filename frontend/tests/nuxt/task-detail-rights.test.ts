import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import TaskDetail from '~/components/task/Detail.vue'

/** The task as the backend answers it for a member: changing follows the task, not the project (F4.5). */
const answer = ref<Record<string, unknown>>({})

function taskFor(own: boolean) {
  return {
    task: {
      id: '7',
      title: 'Bühne planen',
      description: null,
      icon: null,
      status: 'OPEN',
      boardPosition: 0,
      dueDate: null,
      commit: null,
      userStory: null,
      assignee: null,
      labels: [],
      viewerCanUpdate: own,
      viewerCanAssign: false,
      viewerCanAssignSelf: true,
      viewerCanDelete: false,
      todo: { id: '3', title: 'Programm', milestone: { id: '2', title: 'Fest', project: { id: '1', name: 'Sommerfest', labelSet: null } } },
      comments: []
    }
  }
}

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: answer, loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('TaskDetail', () => {
  const props = { taskId: '7', members: [], can: { move: true, comment: true } }
  const editButton = (detail: { findAll: (selector: string) => { text: () => string }[] }) => detail.findAll('wa-button').find(button => button.text() === 'Ändern')

  it('offers "Ändern" for the own task of a member', async () => {
    answer.value = taskFor(true)
    const detail = await mountSuspended(TaskDetail, { props })
    await flushPromises()

    expect(editButton(detail)).toBeDefined()
  })

  it('does not offer it for someone else\'s task', async () => {
    answer.value = taskFor(false)
    const detail = await mountSuspended(TaskDetail, { props })
    await flushPromises()

    expect(editButton(detail)).toBeUndefined()
  })
})
