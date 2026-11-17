import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import ProjectLabelsFormModal from '~/components/project/LabelsFormModal.vue'
import ProjectTemplateLabelsModal from '~/components/project/TemplateLabelsModal.vue'
import { installElementInternals } from './support/element-internals'

const { update, apply, templates } = vi.hoisted(() => ({ update: vi.fn(), apply: vi.fn(), templates: { value: { templates: [] as unknown[] } } }))

vi.mock('@vue/apollo-composable', () => ({
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: document.definitions[0]?.name?.value === 'UpdateProjectLabels' ? update : apply
  }),
  useQuery: () => ({ result: ref(templates.value), loading: ref(false), refetch: vi.fn() }),
  useApolloClient: () => ({ resolveClient: () => ({ readQuery: () => null, query: async () => ({ data: null }), refetchQueries: async () => [] }) })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

installElementInternals()

beforeEach(() => {
  update.mockReset().mockResolvedValue({ data: {} })
  apply.mockReset().mockResolvedValue({ data: {} })
})

const labels = [
  { id: '1', key: 'bug', names: [{ locale: 'DE', name: 'Fehler' }], category: null, color: 'RED', taskCount: 4 },
  { id: '2', key: 'idee', names: [{ locale: 'DE', name: 'Idee' }], category: null, color: 'BLUE', taskCount: 3 },
  { id: '3', key: 'lose', names: [{ locale: 'DE', name: 'Lose' }], category: null, color: 'GRAY', taskCount: 0 }
]

describe('ProjectLabelsFormModal', () => {
  it('asks before taking labels away and names the labels and the tasks', async () => {
    const form = await mountSuspended(ProjectLabelsFormModal, { props: { open: true, projectId: '5', locale: 'de', labels } })

    await form.find('wa-button[aria-label="«Fehler» entfernen"]').trigger('click')
    await form.find('wa-button[aria-label="«Idee» entfernen"]').trigger('click')
    await form.find('form').trigger('submit')
    await flushPromises()

    expect(update).not.toHaveBeenCalled()
    expect(form.text()).toContain('2 Labels werden gelöscht und von 7 Tasks entfernt.')
  })

  it('saves without asking when nothing goes', async () => {
    const form = await mountSuspended(ProjectLabelsFormModal, { props: { open: true, projectId: '5', locale: 'de', labels } })

    await form.find('form').trigger('submit')
    await flushPromises()

    expect(update).toHaveBeenCalledWith({
      projectId: '5',
      labels: [
        { key: 'bug', names: [{ locale: 'DE', name: 'Fehler' }], category: null, color: 'RED' },
        { key: 'idee', names: [{ locale: 'DE', name: 'Idee' }], category: null, color: 'BLUE' },
        { key: 'lose', names: [{ locale: 'DE', name: 'Lose' }], category: null, color: 'GRAY' }
      ]
    })
  })
})

describe('ProjectTemplateLabelsModal', () => {
  const template = {
    id: '9',
    name: 'Software',
    labels: [
      { key: 'bug', name: 'Fehler', names: [{ locale: 'DE', name: 'Fehler' }, { locale: 'EN', name: 'Bug' }], color: 'RED' },
      { key: 'feature', name: 'Feature', names: [{ locale: 'DE', name: 'Feature' }], color: 'GREEN' }
    ]
  }

  async function mountModal(projectLabels = labels) {
    templates.value = { templates: [template] }

    return mountSuspended(ProjectTemplateLabelsModal, { props: { open: true, projectId: '5', locale: 'en', labels: projectLabels } })
  }

  it('shows what replacing does to the labels and their tasks', async () => {
    const modal = await mountModal()
    const select = modal.find('wa-select').element as HTMLElement & { value: string }
    Object.defineProperty(select, 'value', { value: '9', writable: true, configurable: true })
    select.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(modal.text()).toContain('Neu: 1')
    const radios = modal.find('wa-radio-group').element as HTMLElement & { value: string }
    Object.defineProperty(radios, 'value', { value: 'REPLACE', writable: true, configurable: true })
    radios.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(modal.text()).toContain('Angepasst: 1')
    expect(modal.text()).toContain('Bleiben am Ende, weil Tasks sie tragen: 1')
    expect(modal.text()).toContain('Werden gelöscht: 1')
    expect(modal.text()).toContain('Danach 3 Labels im Projekt.')
    expect(modal.text()).toContain('Bei 1 Labels der Vorlage gibt es keinen Namen in Englisch')
  })

  it('takes the labels over with the chosen mode', async () => {
    const modal = await mountModal()
    const select = modal.find('wa-select').element as HTMLElement & { value: string }
    Object.defineProperty(select, 'value', { value: '9', writable: true, configurable: true })
    select.dispatchEvent(new Event('change'))
    await flushPromises()

    await modal.findAll('wa-button').find(button => button.text() === 'Übernehmen')!.trigger('click')
    await flushPromises()

    expect(apply).toHaveBeenCalledWith({ projectId: '5', templateId: '9', mode: 'APPEND' })
  })

  it('refuses a result of more than 30 labels', async () => {
    const many = Array.from({ length: 30 }, (_, index) => ({ id: `${index}`, key: `l${index}`, names: [{ locale: 'DE', name: `L${index}` }], category: null, color: 'GRAY', taskCount: 0 }))
    const modal = await mountModal(many)
    const select = modal.find('wa-select').element as HTMLElement & { value: string }
    Object.defineProperty(select, 'value', { value: '9', writable: true, configurable: true })
    select.dispatchEvent(new Event('change'))
    await flushPromises()

    expect(modal.text()).toContain('mehr als 30 Labels')
    expect((modal.findAll('wa-button').find(button => button.text() === 'Übernehmen')!.element as unknown as { disabled: boolean }).disabled).toBe(true)
  })
})
