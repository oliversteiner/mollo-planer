import { describe, expect, it } from 'vitest'
import { filterByProject, projectOptions } from '../../app/utils/project-filter'

interface Item {
  id: string
  project: { id: string, name: string }
}

const items: Item[] = [
  { id: 't1', project: { id: '2', name: 'Sommerfest 2027' } },
  { id: 't2', project: { id: '1', name: 'Website-Relaunch' } },
  { id: 't3', project: { id: '2', name: 'Sommerfest 2027' } }
]

describe('filterByProject', () => {
  it('keeps all items when no project is chosen', () => {
    expect(filterByProject(items, null, item => item.project.id).map(item => item.id)).toEqual(['t1', 't2', 't3'])
    expect(filterByProject(items, '', item => item.project.id)).toHaveLength(3)
  })

  it('keeps only the items of the chosen project', () => {
    expect(filterByProject(items, '2', item => item.project.id).map(item => item.id)).toEqual(['t1', 't3'])
  })

  it('returns an empty list for a project without items, and never changes the input', () => {
    expect(filterByProject(items, '99', item => item.project.id)).toEqual([])
    expect(items).toHaveLength(3)
  })
})

describe('projectOptions', () => {
  it('lists each project once, sorted by name', () => {
    expect(projectOptions(items, item => item.project)).toEqual([
      { id: '1', name: 'Website-Relaunch' },
      { id: '2', name: 'Sommerfest 2027' }
    ].sort((a, b) => a.name.localeCompare(b.name)))
  })

  it('is empty without items', () => {
    expect(projectOptions([], (item: Item) => item.project)).toEqual([])
  })
})
