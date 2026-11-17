import { mountSuspended } from '@nuxt/test-utils/runtime'
import { beforeAll, describe, expect, it } from 'vitest'
import LabelEditor from '~/components/label/Editor.vue'
import type { LabelRow } from '~/utils/labels'
import { rowFromLabel } from '~/utils/labels'

beforeAll(async () => {
  await useNuxtApp().$i18n.setLocale('de')
})

const rows = (): LabelRow[] => [
  rowFromLabel({ key: 'bug', names: [{ locale: 'DE', name: 'Fehler' }, { locale: 'EN', name: 'Bug' }], category: 'type', color: 'RED' }),
  rowFromLabel({ key: 'idee', names: [{ locale: 'DE', name: 'Idee' }], category: 'type', color: 'BLUE' })
]

async function mountEditor(modelValue: LabelRow[], props: Record<string, unknown> = {}) {
  const updates: LabelRow[][] = []
  const editor = await mountSuspended(LabelEditor, {
    props: { 'modelValue': modelValue, 'required': 'DE', 'onUpdate:modelValue': (value: LabelRow[]) => updates.push(value), ...props }
  })

  return { editor, updates }
}

describe('LabelEditor', () => {
  it('shows a field per language, one of them required', async () => {
    const { editor } = await mountEditor(rows())
    const inputs = editor.findAll('wa-input[data-locale]')

    expect(inputs).toHaveLength(4)
    const required = (locale: string) => inputs.filter(input => input.attributes('data-locale') === locale).map(input => (input.element as unknown as { required: boolean }).required)

    expect(required('DE')).toEqual([true, true])
    expect(required('EN')).toEqual([false, false])
  })

  it('adds a row in the category of the one above, with a free colour', async () => {
    const { editor, updates } = await mountEditor(rows())

    await editor.findAll('wa-button').find(button => button.text() === 'Label hinzufügen')!.trigger('click')

    expect(updates.at(-1)).toHaveLength(3)
    expect(updates.at(-1)![2]).toMatchObject({ category: 'type', names: { DE: '', EN: '' } })
    expect(updates.at(-1)![2]!.color).not.toBe('RED')
  })

  it('moves and removes rows', async () => {
    const { editor, updates } = await mountEditor(rows())

    await editor.find('wa-button[aria-label="«Idee» nach oben"]').trigger('click')
    expect(updates.at(-1)!.map(row => row.key)).toEqual(['idee', 'bug'])

    await editor.find('wa-button[aria-label="«Fehler» entfernen"]').trigger('click')
    expect(updates.at(-1)!.map(row => row.key)).toEqual(['idee'])
  })

  it('says what is missing and what a missing translation means', async () => {
    const missing = rowFromLabel({ key: 'x', names: [{ locale: 'EN', name: 'Only English' }], color: 'RED' })
    const { editor } = await mountEditor([...rows(), missing])

    expect(editor.text()).toContain('Der Name in Deutsch fehlt.')
    expect(editor.text()).toContain('Ohne Namen in Englisch erscheint das Label dort mit dem Namen in Deutsch.')
  })

  it('stops adding at 30 labels and shows the message of the server for a row', async () => {
    const many = Array.from({ length: 30 }, (_, index) => rowFromLabel({ key: `l${index}`, names: [{ locale: 'DE', name: `L${index}` }], color: 'GRAY' }))
    const { editor } = await mountEditor(many, { errors: { 'labels.1.names': 'Jeder Name darf in einer Sprache nur einmal vorkommen.' } })

    expect((editor.findAll('wa-button').find(button => button.text() === 'Label hinzufügen')!.element as unknown as { disabled: boolean }).disabled).toBe(true)
    expect(editor.text()).toContain('Mehr als 30 Labels sind nicht möglich.')
    expect(editor.text()).toContain('Jeder Name darf in einer Sprache nur einmal vorkommen.')
  })
})
