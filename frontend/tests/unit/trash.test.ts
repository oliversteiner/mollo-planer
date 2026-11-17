import { describe, expect, it } from 'vitest'
import { deletionIdOf } from '../../app/composables/useDelete'
import { trashSummary } from '../../app/utils/trash'

describe('deletionIdOf', () => {
  it('finds the deletion id in the result of any delete mutation', () => {
    expect(deletionIdOf({ data: { deleteTask: { id: '7', deletionId: 'd-1' } } })).toBe('d-1')
    expect(deletionIdOf({ data: { deleteMilestone: { id: '2', deletionId: 'd-2' } } })).toBe('d-2')
  })

  it('gives null for a failed or empty result', () => {
    expect(deletionIdOf(null)).toBeNull()
    expect(deletionIdOf({ data: { deleteTask: null } })).toBeNull()
    expect(deletionIdOf({ data: { deleteTask: { id: '7', deletionId: null } } })).toBeNull()
  })
})

describe('trashSummary', () => {
  const name = (type: string, count: number) => `${count} ${type}`

  it('lists what went with a deletion from the top down', () => {
    expect(trashSummary([{ type: 'TASK', count: 12 }, { type: 'TODO', count: 3 }], name)).toBe('3 TODO, 12 TASK')
  })

  it('is empty when nothing went with it', () => {
    expect(trashSummary([], name)).toBe('')
    expect(trashSummary([{ type: 'COMMENT', count: 0 }], name)).toBe('')
  })
})
