import { describe, expect, it } from 'vitest'
import { freeColor, labelInputs, lacksName, nameFallbacks, newRow, removedLabels, rowFromLabel, templateLabelsPlan } from '../../app/utils/labels'

const label = (key: string, de: string, en?: string, extra: Record<string, unknown> = {}) => ({
  key,
  names: [{ locale: 'DE', name: de }, ...(en ? [{ locale: 'EN', name: en }] : [])],
  color: 'RED',
  ...extra
})

describe('label rows', () => {
  it('has a name per language and keeps the key of an existing label', () => {
    const row = rowFromLabel(label('bug', 'Fehler', 'Bug', { category: 'type', taskCount: 3 }))

    expect(row).toMatchObject({ key: 'bug', names: { DE: 'Fehler', EN: 'Bug' }, category: 'type', color: 'RED', taskCount: 3 })
    expect(rowFromLabel(label('x', 'Nur Deutsch')).names).toEqual({ DE: 'Nur Deutsch', EN: '' })
  })

  it('sends a key only for a label that exists, and only the names that are filled in', () => {
    const existing = rowFromLabel(label('bug', 'Fehler', 'Bug'))
    const added = newRow([existing])
    added.names.DE = '  Neu '

    expect(labelInputs([existing, added])).toEqual([
      { key: 'bug', names: [{ locale: 'DE', name: 'Fehler' }, { locale: 'EN', name: 'Bug' }], category: null, color: 'RED' },
      { names: [{ locale: 'DE', name: 'Neu' }], category: null, color: expect.any(String) }
    ])
  })

  it('gives a new label the first colour not used yet, in the category of the one above', () => {
    const rows = [rowFromLabel({ ...label('a', 'A'), color: 'RED', category: 'Phase' })]

    expect(freeColor(rows)).toBe('ORANGE')
    expect(newRow(rows)).toMatchObject({ category: 'Phase', color: 'ORANGE', names: { DE: '', EN: '' } })
  })

  it('finds a row without the name the project needs', () => {
    expect(lacksName(rowFromLabel(label('a', 'A')), 'EN')).toBe(true)
    expect(lacksName(rowFromLabel(label('a', 'A', 'A')), 'EN')).toBe(false)
  })

  it('counts the labels and tasks that go when a label is taken out', () => {
    const original = [{ key: 'a', taskCount: 2 }, { key: 'b', taskCount: 0 }, { key: 'c', taskCount: 5 }]
    const rows = [rowFromLabel(label('b', 'B')), { ...newRow([]), names: { DE: 'Neu', EN: '' } }]

    expect(removedLabels(original, rows)).toEqual({ count: 2, tasks: 7 })
    expect(removedLabels(original, [])).toEqual({ count: 3, tasks: 7 })
  })
})

describe('taking over the labels of a template', () => {
  const project = [{ key: 'bug', taskCount: 4 }, { key: 'mine', taskCount: 2 }, { key: 'lonely', taskCount: 0 }]
  const template = [{ key: 'bug' }, { key: 'feature' }]

  it('appends only what is missing', () => {
    expect(templateLabelsPlan(project, template, 'APPEND')).toEqual({ added: 1, updated: 0, kept: 3, removed: 0, total: 4 })
  })

  it('replaces: updates the same keys, keeps used labels, deletes unused ones', () => {
    expect(templateLabelsPlan(project, template, 'REPLACE')).toEqual({ added: 1, updated: 1, kept: 1, removed: 1, total: 3 })
  })

  it('counts the labels without a name in the language of the project', () => {
    expect(nameFallbacks([{ names: [{ locale: 'DE' }] }, { names: [{ locale: 'DE' }, { locale: 'EN' }] }], 'EN')).toBe(1)
  })
})
