import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import Ideas from '~/pages/ideas.vue'

const queryVariables = vi.hoisted(() => [] as unknown[])

vi.mock('@vue/apollo-composable', async (original) => {
  const actual = await original<typeof import('@vue/apollo-composable')>()

  return {
    ...actual,
    useQuery: (_query: unknown, variables: () => unknown) => {
      queryVariables.push(variables)

      return {
        loading: ref(false),
        result: ref({
          ideas: {
            data: [
              { id: '1', title: 'Podcast starten', description: null, status: 'OPEN', draft: null, createdAt: '2026-11-18T10:00:00+00:00', adoptedAt: null, viewerCanUpdate: true, project: null, author: { id: '1', name: 'Ich' }, todos: [] },
              { id: '2', title: 'Fremde Idee', description: null, status: 'ADOPTED', draft: null, createdAt: '2026-11-18T10:00:00+00:00', adoptedAt: null, viewerCanUpdate: false, project: null, author: { id: '2', name: 'Lindita' }, todos: [{ id: '5', title: 'Daraus', project: { id: '9' } }] }
            ]
          }
        })
      }
    },
    useMutation: () => ({ mutate: vi.fn(), loading: ref(false) })
  }
})

mockNuxtImport('useDelete', () => () => ({ remove: vi.fn() }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

describe('ideas page', () => {
  it('lists ideas, offers own ones the actions and links the todos of an adopted one', async () => {
    const page = await mountSuspended(Ideas)

    expect(page.text()).toContain('Podcast starten')
    expect(page.text()).toContain('Mit dem Assistenten planen')
    expect(page.text()).toContain('Daraus')
    // the foreign idea has no actions: one footer only
    expect(page.findAll('wa-card')).toHaveLength(2)
    expect(page.findAll('wa-card')[1]!.text()).not.toContain('Verwerfen')
  })

  it('filters by status: "Offen" is the start, the buttons switch', async () => {
    const page = await mountSuspended(Ideas)
    const variables = queryVariables.at(-1) as () => { status: string | null }

    expect(variables().status).toBe('OPEN')

    await page.findAll('wa-button').find(button => button.text() === 'Angenommen')!.trigger('click')

    expect(variables().status).toBe('ADOPTED')
  })
})
