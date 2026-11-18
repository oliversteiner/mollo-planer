import { describe, expect, it } from 'vitest'
import { countsText, ideaTitle, isNewId, newCounts, readPlan, textDocument, withoutStruck } from '../../app/utils/assistant'

const text = JSON.stringify({
  format: 'planer-v2',
  revision: 1,
  project: { name: 'P', locale: 'de', members: [] },
  milestones: [
    { id: 'm-1', title: 'Alt', todos: [{ id: 't-1', title: 'Vorhanden', tasks: [{ id: 'k-1', title: 'Auch', status: 'open' }, { id: 'k-new-1', title: 'Neu', status: 'planned' }] }] },
    { id: 'm-new-1', title: 'Neuer Milestone', todos: [{ id: 't-new-1', title: 'T', tasks: [{ id: 'k-new-2', title: 'K', status: 'planned' }] }] }
  ]
})

describe('assistant plan', () => {
  it('knows new ids', () => {
    expect(isNewId('k-new-3')).toBe(true)
    expect(isNewId('k-12')).toBe(false)
  })

  it('reads nothing from empty or broken text', () => {
    expect(readPlan(null)).toBeNull()
    expect(readPlan('{')).toBeNull()
    expect(readPlan(text)?.project.name).toBe('P')
  })

  it('a struck milestone takes its todos and tasks, a struck task only itself', () => {
    const document = readPlan(text)!

    expect(withoutStruck(document, new Set(['m-new-1'])).milestones.map(m => m.id)).toEqual(['m-1'])
    expect(withoutStruck(document, new Set(['k-new-1'])).milestones[0]!.todos[0]!.tasks.map(k => k.id)).toEqual(['k-1'])
    expect(withoutStruck(document, new Set(['t-1'])).milestones[0]!.todos).toEqual([])
  })

  it('counts what is new, without the struck', () => {
    const document = readPlan(text)!

    expect(newCounts(document)).toEqual({ milestones: 1, todos: 1, tasks: 2 })
    expect(newCounts(document, new Set(['m-new-1']))).toEqual({ milestones: 0, todos: 0, tasks: 1 })
  })

  it('suggests an idea title from the first new todo, else task, else milestone, or the name of a new project', () => {
    const document = readPlan(text)!

    expect(ideaTitle(document, false, 'Hallo')).toBe('T')
    expect(ideaTitle(document, true, 'Hallo')).toBe('P')
    expect(ideaTitle(null, false, 'Erste Zeile\nzweite')).toBe('Erste Zeile')
  })

  it('makes paragraphs of a text', () => {
    expect(textDocument('Eins\n\nZwei')?.content).toHaveLength(2)
    expect(textDocument('  ')).toBeNull()
  })

  it('words the counts without zeros and with their own plural', () => {
    const name = (kind: string, count: number) => `${count} ${kind}${count === 1 ? '' : 's'}`

    expect(countsText({ milestones: 0, todos: 1, tasks: 2 }, name)).toBe('1 todo, 2 tasks')
    expect(countsText({ milestones: 0, todos: 0, tasks: 0 }, name)).toBeNull()
  })
})
