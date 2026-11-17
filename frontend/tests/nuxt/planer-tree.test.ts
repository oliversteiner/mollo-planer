import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import PlanerTree from '~/components/planer/Tree.vue'
import { readPlanerFile, type PlanerChange } from '~/utils/planer'

const text = readFileSync(resolve(process.cwd(), '../spec/fixtures/planer-valid.planer.json'), 'utf8')
const read = readPlanerFile(text)
if (!('document' in read)) {
  throw new Error('fixture is not a planer file')
}

const document = read.document

describe('PlanerTree', () => {
  it('shows milestones, todos and tasks of a document', async () => {
    const tree = await mountSuspended(PlanerTree, { props: { document } })

    expect(tree.findAll('wa-tree-item').length).toBe(document.milestones.reduce((sum, m) => sum + 1 + m.todos.length + m.todos.reduce((n, t) => n + t.tasks.length, 0), 0))
    expect(tree.text()).toContain('Schema-Refactoring')
    expect(tree.text()).toContain('Sperre für Milestones ergänzen')
    expect(tree.text()).toContain('1.4')
  })

  it('marks new, changed and moved places with text and icon, and lists what is deleted', async () => {
    const changes: PlanerChange[] = [
      { kind: 'CREATE', path: 'milestones[0].todos[0].tasks[0]', id: 'k-new-1', fields: ['title'] },
      { kind: 'UPDATE', path: 'milestones[1]', id: 'm-2', fields: ['title', 'dueDate'] },
      { kind: 'MOVE', path: 'milestones[1].todos[0]', id: 't-2', fields: ['milestone'] },
      { kind: 'DELETE', path: 'k-7', id: 'k-7', fields: [] }
    ]
    const tree = await mountSuspended(PlanerTree, { props: { document, changes } })
    const marks = tree.findAll('wa-badge.planer-mark')

    expect(marks.map(mark => (mark.find('wa-icon').element as unknown as { name: string }).name)).toEqual(['plus', 'pen', 'arrows-up-down'])
    expect(marks[1]!.text()).toContain('Title, Due date')
    expect(tree.text()).toContain('Task k-7')
    expect(tree.find('.planer-removed').exists()).toBe(true)
  })

  it('says so when the plan has no milestones', async () => {
    const tree = await mountSuspended(PlanerTree, { props: { document: { ...document, milestones: [] } } })

    expect(tree.text()).toContain('no milestones')
  })
})
