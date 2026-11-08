import { describe, expect, it } from 'vitest'
import { locate, moveInColumns } from '../../app/utils/kanban'

const board = () => [
  { status: 'OPEN', tasks: [{ id: '1' }, { id: '2' }, { id: '3' }] },
  { status: 'IN_PROGRESS', tasks: [{ id: '4' }] },
  { status: 'DONE', tasks: [] as { id: string }[] }
]

const ids = (columns: ReturnType<typeof board>) => columns.map(column => column.tasks.map(task => task.id))

describe('moveInColumns', () => {
  it('moves into another column at the position', () => {
    expect(ids(moveInColumns(board(), '2', 'IN_PROGRESS', 0))).toEqual([['1', '3'], ['2', '4'], []])
  })

  it('reorders within a column like the backend (position counts without the task)', () => {
    expect(ids(moveInColumns(board(), '1', 'OPEN', 2))).toEqual([['2', '3', '1'], ['4'], []])
    expect(ids(moveInColumns(board(), '3', 'OPEN', 0))).toEqual([['3', '1', '2'], ['4'], []])
  })

  it('puts a task last when the position is beyond the end, and ignores unknown tasks', () => {
    expect(ids(moveInColumns(board(), '1', 'DONE', 100000))).toEqual([['2', '3'], ['4'], ['1']])
    expect(ids(moveInColumns(board(), '9', 'DONE', 0))).toEqual(ids(board()))
  })

  it('does not change the original columns', () => {
    const columns = board()
    moveInColumns(columns, '1', 'DONE', 0)

    expect(ids(columns)).toEqual(ids(board()))
  })
})

describe('locate', () => {
  it('finds column and position', () => {
    expect(locate(board(), '3')).toEqual({ status: 'OPEN', position: 2 })
    expect(locate(board(), '9')).toBeNull()
  })
})
