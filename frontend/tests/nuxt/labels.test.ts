import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import LabelPicker from '~/components/LabelPicker.vue'
import TaskLabels from '~/components/TaskLabels.vue'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const labels = [
  { id: '2', name: 'Fehler', category: 'Typ', color: 'RED', position: 3 },
  { id: '1', name: 'Recherche', category: 'Phase', color: 'BLUE', position: 0 },
  { id: '3', name: 'Design', category: 'Phase', color: 'PINK', position: 1 }
]

describe('TaskLabels', () => {
  it('shows the labels in the order of their set with a dot in their hue', async () => {
    const list = await mountSuspended(TaskLabels, { props: { labels } })
    const items = list.findAll('li')

    expect(items.map(item => item.text())).toEqual(['Recherche', 'Design', 'Fehler'])
    expect(items[0]!.find('.task-label-dot').attributes('style')).toContain('var(--wa-color-blue-60)')
  })

  it('marks labels that are no longer in the set of the project', async () => {
    const list = await mountSuspended(TaskLabels, { props: { labels, setLabelIds: ['1', '3'] } })
    const outside = list.findAll('li').filter(item => item.attributes('data-outside') !== undefined)

    expect(outside.map(item => item.text())).toEqual(['Fehler (nicht mehr im Label-Set des Projekts)'])
  })

  it('shows nothing without labels', async () => {
    expect((await mountSuspended(TaskLabels, { props: { labels: [] } })).find('ul').exists()).toBe(false)
  })
})

describe('LabelPicker', () => {
  it('offers the labels grouped by category, the old ones in a group of their own', async () => {
    const picker = await mountSuspended(LabelPicker, {
      props: { modelValue: ['1'], labels, extra: [{ id: '9', name: 'Location', color: 'PURPLE', position: 0 }] }
    })

    expect(picker.findAll('small').map(group => group.text())).toEqual(['Phase', 'Typ', 'Nicht mehr im Set'])
    expect(picker.findAll('wa-option').map(option => option.text())).toEqual(['Recherche', 'Design', 'Fehler', 'Location'])
    expect((picker.element as unknown as { value: string[] }).value).toEqual(['1'])
  })

  it('writes the chosen labels back as a list', async () => {
    const picker = await mountSuspended(LabelPicker, { props: { modelValue: [], labels } })

    Object.defineProperty(picker.element, 'value', { value: ['1', '2'], configurable: true })
    picker.element.dispatchEvent(new Event('change'))

    expect(picker.emitted('update:modelValue')).toEqual([[['1', '2']]])
  })
})
