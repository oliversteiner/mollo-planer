/**
 * Moving a task between and within Kanban columns, the same way the
 * backend's KanbanOrdering does it: the task is taken out of its column
 * and inserted at `position` of the target column (beyond the end means
 * last). Used for showing a move at once, before the server confirms it.
 */
export interface KanbanColumnLike<T extends { id: string }> {
  status: string
  tasks: T[]
}

export function moveInColumns<C extends KanbanColumnLike<{ id: string }>>(
  columns: C[],
  taskId: string,
  status: string,
  position: number
): C[] {
  const task = columns.flatMap(column => column.tasks).find(candidate => candidate.id === taskId)

  if (!task) {
    return columns
  }

  return columns.map((column) => {
    const tasks = column.tasks.filter(candidate => candidate.id !== taskId)

    if (column.status === status) {
      tasks.splice(Math.max(0, Math.min(position, tasks.length)), 0, task)
    }

    return { ...column, tasks } as C
  })
}

/** Column and position of a task, or null if it is not on the board. */
export function locate<T extends { id: string }>(columns: KanbanColumnLike<T>[], taskId: string): { status: string, position: number } | null {
  for (const column of columns) {
    const position = column.tasks.findIndex(task => task.id === taskId)

    if (position !== -1) {
      return { status: column.status, position }
    }
  }

  return null
}
