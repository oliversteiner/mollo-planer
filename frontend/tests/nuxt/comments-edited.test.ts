import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import TaskComments from '~/components/task/Comments.vue'

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: ref(null), loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

function comment(id: string, editedAt: string | null, isAgent = false) {
  return {
    id,
    body: { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: `Kommentar ${id}` }] }] },
    createdAt: '2026-11-04 09:15:00',
    editedAt,
    viewerCanEdit: false,
    viewerCanDelete: false,
    author: { id: '1', name: 'Ben Müller', avatarUrl: null, isAgent }
  }
}

describe('TaskComments', () => {
  it('says "edited" only when the text was changed (F5.6)', async () => {
    const view = await mountSuspended(TaskComments, {
      props: { taskId: '2', canComment: false, comments: [comment('1', null), comment('2', '2026-11-05 10:00:00')] }
    })

    // The tests run in English: „bearbeitet“ in German.
    expect(view.findAll('.comment-quiet').filter(element => element.text() === 'edited')).toHaveLength(1)
  })

  it('marks the comment of an agent account', async () => {
    const view = await mountSuspended(TaskComments, {
      props: { taskId: '2', canComment: false, comments: [comment('1', null), comment('2', null, true)] }
    })

    expect(view.findAll('wa-badge')).toHaveLength(1)
    expect(view.find('wa-badge').text()).toBe('Agent')
  })
})
