/** How long the undo message stays (docs/auftrag-m2.md §8: about 8 seconds). */
export const UNDO_DURATION = 8000

interface DeleteOptions<T> {
  /** Runs after a successful delete, for example to close a panel. */
  onDeleted?: (target: T) => void
}

/**
 * The deletionId in the result of a delete mutation ({ data: { deleteTask: { deletionId } } }).
 */
export function deletionIdOf(result: unknown): string | null {
  const data = (result as { data?: Record<string, { deletionId?: string | null } | null> } | null | undefined)?.data

  for (const value of Object.values(data ?? {})) {
    if (value?.deletionId) {
      return value.deletionId
    }
  }

  return null
}

/**
 * The one way the app deletes project elements (milestones, todos, tasks,
 * comments): at once, into the trash, then a message „Gelöscht“ with
 * „Rückgängig“ for about 8 seconds (docs/auftrag-m2.md §4.1). Undo restores
 * the deletion and loads the project again (useTrashRestore). Afterwards
 * the trash page still has it. Deleting a project and deleting for good keep
 * their confirmation (useConfirmedDelete).
 *
 * When the deleted element held the focus, the focus moves to „Rückgängig“,
 * so the keyboard does not lose its place.
 */
export function useDelete<T>(remove: (target: T) => Promise<unknown>, options: DeleteOptions<T> = {}) {
  const { t } = useI18n()
  const toast = useToast()
  const restore = useTrashRestore()

  async function run(target: T): Promise<void> {
    const deletionId = deletionIdOf(await remove(target).catch(() => null))

    if (deletionId === null) {
      return
    }

    options.onDeleted?.(target)

    const item = toast.add({
      title: t('trash.deleted'),
      color: 'neutral',
      duration: UNDO_DURATION,
      action: { label: t('trash.undo'), run: () => restore(deletionId) }
    })

    await nextTick()
    if (document.activeElement === null || document.activeElement === document.body) {
      item.querySelector<HTMLElement>('.toast-action')?.focus()
    }
  }

  return { remove: run }
}
