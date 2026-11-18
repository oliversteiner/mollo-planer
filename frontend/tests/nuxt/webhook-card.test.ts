import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import WebhookCard from '~/components/planer/WebhookCard.vue'

/** The notification card of the project settings (docs/auftrag-m5.md §4.5): states and what it never shows. */
const answer = ref<{ project: { id: string, webhook: Record<string, unknown> | null } }>({ project: { id: '1', webhook: null } })

vi.mock('@vue/apollo-composable', () => ({
  useQuery: () => ({ result: answer, loading: ref(false), refetch: vi.fn() }),
  useMutation: () => ({ mutate: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const github = { kind: 'GITHUB', target: 'oliver/podcast', hasSecret: true, lastSentAt: null, lastSentRevision: null, lastFailedAt: null, lastError: null }

async function card() {
  const mounted = await mountSuspended(WebhookCard, { props: { projectId: '1' } })
  await flushPromises()

  return mounted
}

type Card = Awaited<ReturnType<typeof card>>

/** The wa-input with this name; its label, type and value are properties of the element. */
const input = (wrapper: Card, name: string) => wrapper.findAll('wa-input').find(found => (found.element as unknown as { name?: string }).name === name || found.attributes('name') === name)
const prop = (wrapper: ReturnType<typeof input>, key: 'label' | 'type' | 'value') => (wrapper!.element as unknown as Record<string, string>)[key]

/** A button of the card itself, not of the dialog that asks before removing. */
const button = (wrapper: Card, text: string) => wrapper.findAll('wa-button').find(found => found.text().includes(text) && found.element.closest('wa-dialog') === null)

describe('WebhookCard', () => {
  it('asks for repository and token when nothing is set up, with no test and no remove', async () => {
    answer.value = { project: { id: '1', webhook: null } }
    const wrapper = await card()

    expect(prop(input(wrapper, 'target'), 'label')).toBe('Repository')
    expect(input(wrapper, 'secret')).toBeDefined()
    expect(prop(input(wrapper, 'secret'), 'type')).toBe('password')
    expect(button(wrapper, 'Testen')).toBeUndefined()
    expect(button(wrapper, 'Entfernen')).toBeUndefined()
    expect(prop(input(wrapper, 'secret'), 'label')).toBe('Token')
  })

  it('shows "Token gespeichert" with "Ersetzen" after saving, never a value, and offers test and remove', async () => {
    answer.value = { project: { id: '1', webhook: github } }
    const wrapper = await card()

    expect(wrapper.text()).toContain('Token gespeichert')
    expect(input(wrapper, 'secret')).toBeUndefined()
    expect(button(wrapper, 'Testen')).toBeDefined()
    expect(button(wrapper, 'Entfernen')).toBeDefined()

    await button(wrapper, 'Ersetzen')!.trigger('click')
    expect(input(wrapper, 'secret')).toBeDefined()
    expect(prop(input(wrapper, 'secret'), 'value')).toBe('')
  })

  it('shows the last send, and a failure with its error instead', async () => {
    answer.value = { project: { id: '1', webhook: { ...github, lastSentAt: '2026-11-18T10:00:00+00:00', lastSentRevision: 7 } } }
    expect((await card()).find('[data-status="sent"]').text()).toContain('Revision 7')

    answer.value = { project: { id: '1', webhook: { ...github, lastSentAt: '2026-11-18T10:00:00+00:00', lastSentRevision: 7, lastFailedAt: '2026-11-18T11:00:00+00:00', lastError: 'HTTP 401' } } }
    const failed = (await card()).find('[data-status="failed"]')
    expect(failed.text()).toContain('fehlgeschlagen')
    expect(failed.text()).toContain('HTTP 401')

    answer.value = { project: { id: '1', webhook: { ...github, lastFailedAt: '2026-11-18T11:00:00+00:00', lastError: 'timeout' } } }
    expect((await card()).find('[data-status="failed"]').text()).toContain('Zeitüberschreitung')
  })

  it('says that nothing was sent yet', async () => {
    answer.value = { project: { id: '1', webhook: github } }

    expect((await card()).find('[data-status="none"]').text()).toBe('Noch nichts gesendet.')
  })

  it('has address and secret for the generic kind', async () => {
    answer.value = { project: { id: '1', webhook: { ...github, kind: 'GENERIC', target: 'https://hooks.example.org/x' } } }
    const wrapper = await card()

    expect(prop(input(wrapper, 'target'), 'label')).toBe('Adresse')
    expect(wrapper.text()).toContain('Geheimnis gespeichert')
  })
})
