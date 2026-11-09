import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import LabelSetFormModal from '~/components/label-set/FormModal.vue'

const { createSet, updateSet } = vi.hoisted(() => ({ createSet: vi.fn(), updateSet: vi.fn() }))

vi.mock('@vue/apollo-composable', () => ({
  useMutation: (document: { definitions: { name?: { value: string } }[] }) => ({
    mutate: document.definitions[0]?.name?.value === 'CreateLabelSet' ? createSet : updateSet
  })
}))

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

// happy-dom has no form internals; the submit button (form="…") of Web Awesome reads them.
const prototype = HTMLElement.prototype as unknown as { attachInternals?: () => unknown }
const original = prototype.attachInternals

beforeEach(() => {
  prototype.attachInternals = function () {
    return original?.call(this) ?? { form: null, states: new Set(), setFormValue() {}, setValidity() {}, checkValidity: () => true, reportValidity: () => true, validity: { valid: true }, validationMessage: '', labels: [] }
  }
})

afterEach(() => {
  prototype.attachInternals = original
})

beforeEach(() => {
  createSet.mockReset().mockResolvedValue({ data: {} })
  updateSet.mockReset().mockResolvedValue({ data: {} })
})

const set = {
  id: '7',
  name: 'Bau',
  description: null,
  labels: [
    { id: '1', name: 'Rohbau', category: 'Phase', color: 'ORANGE' },
    { id: '2', name: 'Ausbau', category: 'Phase', color: 'BLUE' }
  ]
}

describe('LabelSetFormModal', () => {
  it('sends the labels in their order, kept ones with their id, new ones without', async () => {
    const form = await mountSuspended(LabelSetFormModal, { props: { target: { set } } })

    await form.findAll('wa-button').find(button => button.text() === 'Label hinzufügen')!.trigger('click')
    const names = form.findAll('wa-input').filter(input => (input.element as unknown as { label: string }).label === 'Label')
    const last = names.at(-1)!.element as HTMLElement & { value: string }
    Object.defineProperty(last, 'value', { value: 'Abnahme', writable: true, configurable: true })
    last.dispatchEvent(new Event('input'))

    await form.find('wa-button[aria-label="«Ausbau» nach oben"]').trigger('click')
    await form.find('form').trigger('submit')
    await flushPromises()

    expect(updateSet).toHaveBeenCalledWith({
      id: '7',
      input: {
        name: 'Bau',
        description: null,
        labels: [
          { id: '2', name: 'Ausbau', category: 'Phase', color: 'BLUE' },
          { id: '1', name: 'Rohbau', category: 'Phase', color: 'ORANGE' },
          { name: 'Abnahme', category: 'Phase', color: 'RED' }
        ]
      }
    })
  })

  it('asks before deleting labels of the set, which also leaves the tasks', async () => {
    const form = await mountSuspended(LabelSetFormModal, { props: { target: { set } } })

    await form.find('wa-button[aria-label="«Rohbau» entfernen"]').trigger('click')
    await form.find('form').trigger('submit')
    await flushPromises()

    expect(updateSet).not.toHaveBeenCalled()
    expect(form.text()).toContain('1 Label wird gelöscht und von allen Tasks entfernt.')
  })

  it('creates a new set', async () => {
    const form = await mountSuspended(LabelSetFormModal, { props: { target: {} } })
    const name = form.findAll('wa-input').find(input => (input.element as unknown as { name: string }).name === 'name')!.element as HTMLElement & { value: string }
    Object.defineProperty(name, 'value', { value: 'Garten', writable: true, configurable: true })
    name.dispatchEvent(new Event('input'))

    await form.find('form').trigger('submit')
    await flushPromises()

    expect(createSet).toHaveBeenCalledWith({ input: { name: 'Garten', description: null, labels: [] } })
  })
})
