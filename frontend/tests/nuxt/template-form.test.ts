import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import TemplateFormModal from '~/components/template/FormModal.vue'
import { installElementInternals } from './support/element-internals'

const { createTemplate, updateTemplate } = vi.hoisted(() => ({ createTemplate: vi.fn(), updateTemplate: vi.fn() }))

vi.mock('@vue/apollo-composable', () => ({
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: document.definitions[0]?.name?.value === 'CreateTemplate' ? createTemplate : updateTemplate
  })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

// happy-dom has no form internals; the submit button (form="…") of Web Awesome reads them.
installElementInternals()
const prototype = HTMLElement.prototype as unknown as { attachInternals?: () => unknown }
const original = prototype.attachInternals

beforeEach(() => {
  createTemplate.mockReset().mockResolvedValue({ data: {} })
  updateTemplate.mockReset().mockResolvedValue({ data: {} })
})

afterEach(() => {
  prototype.attachInternals = original
})

const template = {
  id: '7',
  name: 'Bau',
  description: 'Für Baustellen',
  locale: 'de',
  labels: [
    { key: 'rohbau', names: [{ locale: 'DE', name: 'Rohbau' }, { locale: 'EN', name: 'Shell' }], category: 'Phase', color: 'ORANGE' },
    { key: 'ausbau', names: [{ locale: 'DE', name: 'Ausbau' }], category: 'Phase', color: 'BLUE' }
  ]
}

function typeInto(input: { element: Element }, value: string): void {
  const element = input.element as HTMLElement & { value: string }
  Object.defineProperty(element, 'value', { value, writable: true, configurable: true })
  element.dispatchEvent(new Event('input'))
}

describe('TemplateFormModal', () => {
  it('sends the labels in their order, kept ones with their key, new ones without', async () => {
    const form = await mountSuspended(TemplateFormModal, { props: { target: { template } } })

    await form.findAll('wa-button').find(button => button.text() === 'Label hinzufügen')!.trigger('click')
    const german = form.findAll('wa-input[data-locale="DE"]')
    typeInto(german.at(-1)!, 'Abnahme')

    await form.find('wa-button[aria-label="«Ausbau» nach oben"]').trigger('click')
    await form.find('form').trigger('submit')
    await flushPromises()

    expect(updateTemplate).toHaveBeenCalledWith({
      id: '7',
      input: {
        name: 'Bau',
        description: 'Für Baustellen',
        labels: [
          { key: 'ausbau', names: [{ locale: 'DE', name: 'Ausbau' }], category: 'Phase', color: 'BLUE' },
          { key: 'rohbau', names: [{ locale: 'DE', name: 'Rohbau' }, { locale: 'EN', name: 'Shell' }], category: 'Phase', color: 'ORANGE' },
          { names: [{ locale: 'DE', name: 'Abnahme' }], category: 'Phase', color: expect.any(String) }
        ]
      }
    })
  })

  it('creates a template with its language, and the language cannot be changed afterwards', async () => {
    const form = await mountSuspended(TemplateFormModal, { props: { target: {} } })
    const name = form.findAll('wa-input').find(input => (input.element as unknown as { name: string }).name === 'name')!
    typeInto(name, 'Garten')

    // The language is chosen for a new template: the select is there (the colour selects of the labels aside).
    const languageSelect = (wrapper: typeof form) => wrapper.findAll('wa-select').find(select => (select.element as unknown as { label: string }).label === 'Sprache')
    expect(languageSelect(form)).toBeDefined()

    await form.find('form').trigger('submit')
    await flushPromises()

    expect(createTemplate).toHaveBeenCalledWith({ input: { name: 'Garten', description: null, locale: 'DE', labels: [] } })

    const edit = await mountSuspended(TemplateFormModal, { props: { target: { template } } })
    expect(languageSelect(edit)).toBeUndefined()
  })

  it('shows what the server refuses at the label', async () => {
    updateTemplate.mockRejectedValue({ graphQLErrors: [{ message: 'x', extensions: { validation: { 'input.labels.1.names': ['Jeder Name darf in einer Sprache nur einmal vorkommen.'] } } }] })
    const form = await mountSuspended(TemplateFormModal, { props: { target: { template } } })

    await form.find('form').trigger('submit')
    await flushPromises()

    expect(form.text()).toContain('Jeder Name darf in einer Sprache nur einmal vorkommen.')
  })
})
