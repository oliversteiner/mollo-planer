import { mountSuspended, mockNuxtImport } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import AssistantPage from '~/pages/assistant.vue'

const { draftCalls } = vi.hoisted(() => ({ draftCalls: [] as Array<Record<string, unknown>> }))

vi.mock('@vue/apollo-composable', async (original) => {
  const actual = await original<typeof import('@vue/apollo-composable')>()

  return {
    ...actual,
    useQuery: () => ({ result: ref(null), error: ref(null) }),
    useMutation: (document: unknown) => {
      const operation = (document as { definitions?: Array<{ name?: { value: string } }> }).definitions?.[0]?.name?.value

      return {
        mutate: operation === 'DraftPlan'
          ? vi.fn(async (variables: Record<string, unknown>) => {
              draftCalls.push(variables)

              return {
                data: {
                  draftPlan: { reply: 'Entwurf bereit.', plan: null, projectId: null, templateId: null, hints: [], choices: [], preview: null }
                }
              }
            })
          : vi.fn(),
        loading: ref(false)
      }
    }
  }
})

vi.mock('~/components/rich-text/Editor.vue', () => ({
  default: { template: '<div data-rich-text-editor />' }
}))

mockNuxtImport('useViewer', () => () => ({ viewer: ref({ id: '42' }) }))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

beforeEach(() => {
  draftCalls.length = 0
  sessionStorage.removeItem('assistant-draft-v2:42')
})

describe('assistant page target kind', () => {
  it('sends null targetKind when automatic mode is selected', async () => {
    const page = await mountSuspended(AssistantPage)
    const textarea = page.find('wa-textarea').element as unknown as { value: string }
    textarea.value = 'Bitte planen'
    page.find('wa-textarea').element.dispatchEvent(new Event('input'))
    await page.find('.chat-input form').trigger('submit')
    await flushPromises()

    expect(draftCalls[0]).toMatchObject({ targetKind: null })
    page.unmount()
  })

  it.each(['MILESTONE', 'TODO', 'TASK'] as const)('sends the selected %s target kind with the GraphQL mutation', async (kind) => {
    const page = await mountSuspended(AssistantPage)
    const select = page.find('wa-select').element as unknown as { value: string }
    select.value = kind
    page.find('wa-select').element.dispatchEvent(new Event('change'))

    const textarea = page.find('wa-textarea').element as unknown as { value: string }
    textarea.value = 'Bitte planen'
    page.find('wa-textarea').element.dispatchEvent(new Event('input'))
    await page.find('.chat-input form').trigger('submit')
    await flushPromises()

    expect(draftCalls).toHaveLength(1)
    expect(draftCalls[0]).toMatchObject({ targetKind: kind })
    page.unmount()
  })

  it('restores an explicit target kind from the current user session', async () => {
    sessionStorage.setItem('assistant-draft-v2:42', JSON.stringify({
      messages: [{ role: 'USER', content: 'Vorhandener Chat' }],
      targetKind: 'TASK'
    }))

    const page = await mountSuspended(AssistantPage)
    const select = page.find('wa-select').element as unknown as { value: string, disabled: boolean }

    expect(select.value).toBe('TASK')
    expect(select.disabled).toBe(true)
    page.unmount()
  })

  it('restores older chats without a target kind in automatic mode', async () => {
    sessionStorage.setItem('assistant-draft-v2:42', JSON.stringify({
      messages: [{ role: 'USER', content: 'Alter Chat' }]
    }))

    const page = await mountSuspended(AssistantPage)
    const select = page.find('wa-select').element as unknown as { value: string, disabled: boolean }

    expect(select.value).toBe('')
    expect(select.disabled).toBe(true)
    page.unmount()
  })
})
