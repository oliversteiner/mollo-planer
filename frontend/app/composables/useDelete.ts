import { useConfirmedDelete } from './useConfirmedDelete'

/**
 * The one way the app deletes project elements (milestones, todos, tasks,
 * comments). Today it asks for a confirmation first. The trash (Papierkorb)
 * replaces the confirmation with an undo toast; only this composable changes then.
 *
 * Same API as useConfirmedDelete: ask, confirm, close, target, isOpen.
 */
export function useDelete<T>(remove: (target: T) => Promise<unknown>, options: { onDeleted?: (target: T) => void } = {}) {
  return useConfirmedDelete<T>(remove, options)
}
